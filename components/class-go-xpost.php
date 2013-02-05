<?php

abstract class GO_XPost extends GO_XPost_Migrator
{
	public function __construct( $config )
	{
		$this->property   = $config['property'];
		$this->endpoints  = $config['endpoints'];
		$this->post_types = ( isset( $config['post_types'] ) ) ? array_merge( $config['post_types'], $this->post_types ) : $this->post_types;

		add_action( 'edit_post', array( $this, 'edit_post' ) ); 

		add_action( 'wp_ajax_go_xpost_pull', array( $this, 'send_post' ) );
		add_action( 'wp_ajax_nopriv_go_xpost_pull', array( $this, 'send_post' ) );

		add_action( 'wp_ajax_go_xpost_push', array( $this, 'receive_push' ));
		add_action( 'wp_ajax_nopriv_go_xpost_push', array( $this, 'receive_push' ) );
		
		// Load appropriate filters for this property
		require_once dirname( __DIR__ ) . '/local/class-go-xpost-' . $this->property . '.php'; 
	}//end __construct

	public function process_post( $post_id )
	{		
		// Loop through endpoints and push to them if appropriate
		foreach ( $this->endpoints as $target_property => $endpoint )
		{
			if ( $post_id = apply_filters( 'go_xpost_process_post_' . $this->property, $post_id, $target_property ) )
			{
				apply_filters( 'go_slog', 'go-xpost-start', 'XPost from ' . $this->property . ' to ' . $target_property . ': START!',
					array(
						'post_id'   => $post_id,
						'post_type' => get_post( $post_id )->post_type,
					)
				);
				
				$this->push( $endpoint, $post_id );
			}
		} // END foreach
	} // END process_post

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

		if ( $post_id = apply_filters( 'go_xpost_edit_post_' . $this->property, $post_id ) )
		{				
			$this->process_post( $post_id );
		}
	}//end edit_post
}//end class
