<?php

abstract class GO_XPost extends GO_XPost_Migrator
{
	public $post_types = array(
		'post',
	);

	/**
	 * child classes MUST declare this to process posts
	 */
	abstract protected function process_post( $post_id );

	public function __construct( $endpoint )
	{
		$this->endpoint = $endpoint;

		add_action( 'edit_post', array( $this, 'edit_post' ));

		add_filter( 'go_xpost_pre_save_post', 'go_xpost_pre_save_post' );

		add_action( 'wp_ajax_go_xpost_pull', array( $this, 'send_post' ));
		add_action( 'wp_ajax_nopriv_go_xpost_pull', array( $this, 'send_post' ));

		add_action( 'wp_ajax_go_xpost_push', array( $this, 'receive_push' ));
		add_action( 'wp_ajax_nopriv_go_xpost_push', array( $this, 'receive_push' ));
	}//end __construct

	// hook to the edit_post action, possibly ping other sites with the change
	public function edit_post( $post_id )
	{
		// don't bother with autosaves
		if( defined( 'DOING_AUTOSAVE' ))
		{
			return;
		}//end if

		// check the post_id
		$post_id = $this->sanitize_post_id( $post_id );

		if( ! $post_id )
		{
			return;
		}//end if

		// only act on whitelisted post types
		if( ! in_array( get_post( $post_id )->post_type, $this->post_types ))
		{
			return;
		}//end if

		// don't attempt to crosspost a crosspost
		if( (bool) get_post_meta( $post_id, 'go_mancross_redirect', TRUE ))
		{
			return;
		}//end if

		$this->process_post( $post_id );
	}//end edit_post
}//end class
