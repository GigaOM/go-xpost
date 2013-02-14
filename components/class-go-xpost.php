<?php

/**
 * this class can never be directly instantiated, use one of it's children
 */
abstract class GO_XPost
{
	protected $endpoints = array();
	protected $post_types = array( 'post' );
	protected $property;

	/**
	 * Determine whether a post_id should ping a property
	 * This function must be implemented by a child class
	 *
	 * @param  absint $post_id, string $target_property
	 * @return boolean
	 */
	abstract protected function should_process_post( $post_id, $target_property );

	public function __construct( $config )
	{
		$this->property   = $config['property'];
		go_xpost_util( $this->property );

		$this->endpoints  = $config['endpoints'];
		$this->post_types = ( isset( $config['post_types'] ) ) ? array_merge( $config['post_types'], $this->post_types ) : $this->post_types;

		require __DIR__ . '/class-go-xpost-admin.php';
		new GO_XPost_Admin;

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'edit_post', array( $this, 'edit_post' ) );
		add_action( 'go_xpost_receive_push', array( $this, 'receive_push' ) );
		add_filter( 'go_xpost_post_filter', array( $this, 'post_filter' ) );

		// hook the utility functions in GO_XPost_Utilities to admin ajax requests
		add_action( 'wp_ajax_go_xpost_pull', array( go_xpost_util(), 'send_post' ) );
		add_action( 'wp_ajax_nopriv_go_xpost_pull', array( go_xpost_util(), 'send_post' ) );

		add_action( 'wp_ajax_go_xpost_push', array( go_xpost_util(), 'receive_push' ));
		add_action( 'wp_ajax_nopriv_go_xpost_push', array( go_xpost_util(), 'receive_push' ) );
	}//end __construct

	/**
	 * hook to the edit_post action, possibly ping other sites with the change
	 */
	public function edit_post( $post_id )
	{
		// don't bother with autosaves
		if ( defined( 'DOING_AUTOSAVE' ) )
		{
			return;
		}//end if

		// check the post_id
		$post_id = go_xpost_util()->sanitize_post_id( $post_id );

		if ( ! $post_id )
		{
			return;
		}//end if

		// only act on whitelisted post types
		if ( ! in_array( get_post( $post_id )->post_type, $this->post_types ) )
		{
			return;
		}//end if

		// don't attempt to crosspost a crosspost
		if ( (bool) get_post_meta( $post_id, 'go_mancross_redirect', TRUE ) )
		{
			return;
		}//end if

		// @TODO this filter is not currently implemented anywhere, is it needed?
		if ( $post_id = apply_filters( 'go_xpost_edit_post_' . $this->property, $post_id ) )
		{
			$this->process_post( $post_id );
		}// end if
	}//end edit_post

	/**
	 * Remove the edit_post action when receiving a post
	 */
	public function receive_push( $post )
	{
		// Remove edit_post action so we don't trigger an accidental crosspost
		remove_action( 'edit_post', array( $this, 'edit_post' ));
	}// end receive_push

	/**
	 * Helper function for edit_post, loops over endpoints and calles Go_XPost_Utilities::push()
	 */
	protected function process_post( $post_id )
	{
		// In some cases we may want to receive xposts but not send them
		if ( FALSE == $this->endpoints )
		{
			return;
		}// end if

		// Loop through endpoints and push to them if appropriate
		foreach ( $this->endpoints as $target_property => $endpoint )
		{
			if ( $post_id = $this->should_process_post( $post_id, $target_property ) )
			{
				// log that we are xposting
				apply_filters( 'go_slog', 'go-xpost-start', 'XPost from ' . $this->property . ' to ' . $target_property . ': START!',
					array(
						'post_id'   => $post_id,
						'post_type' => get_post( $post_id )->post_type,
					)
				);

				go_xpost_util()->push( $endpoint, $post_id );
			}// end if
		} // END foreach
	} // END process_post

	/**
	 * Get the $post object before returning it to a property
	 * Default action is to always simply return $post unchanged, however a child class may override
	 *
	 * @param  object $post, string $requesting_property
	 * @return $post WP_Post
	 */
	public function post_filter( $post, $requesting_property )
	{
		return $post;
	}// end post_filter
}//end class