<?php 

class Go_Xpost extends Go_Migrator
{
	var $post_types = array(
		'post',
	);

	public function __construct( $endpoint )
	{
		$this->endpoint = $endpoint;

		add_action( 'edit_post' , array( $this , 'edit_post' ));

		add_filter( 'go_xpost_pre_save_post' , 'go_xpost_pre_save_post' );

		add_action( 'wp_ajax_go_xpost_pull' , array( $this , 'send_post' ));
		add_action( 'wp_ajax_nopriv_go_xpost_pull' , array( $this , 'send_post' ));

		add_action( 'wp_ajax_go_xpost_push' , array( $this , 'receive_push' ));
		add_action( 'wp_ajax_nopriv_go_xpost_push' , array( $this , 'receive_push' ));
	}

	// hook to the edit_post action, possibly ping other sites with the change
	function edit_post( $post_id )
	{
		// don't bother with autosaves
		if( defined( 'DOING_AUTOSAVE' ))
			return;

		// check the post_id
		$post_id = $this->sanitize_post_id( $post_id );
		if( ! $post_id )
			return;

		// only act on whitelisted post types
		if( ! in_array( get_post( $post_id )->post_type , $this->post_types ))
			return;

		// don't attempt to crosspost a crosspost
		if( (bool) get_post_meta( $post_id , 'go_mancross_redirect' , TRUE ))
			return;

		go_slog('go-xpost-start', 'XPost from pC to GO: START!', 
			array(
				'post_id' => $post_id,
				'post_type' => get_post( $post_id )->post_type,
				'go_mancross_redirect' => get_post_meta( $post_id , 'go_mancross_redirect' , TRUE )
			)
		);

		// push all posts	
		$this->push( $this->endpoint , $post_id );

		return;
	}
	// END edit_post

}
// END Go_Xpost