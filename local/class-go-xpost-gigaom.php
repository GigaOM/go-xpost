<?php

class GO_XPost_Gigaom extends GO_XPost
{
	public function __construct()
	{
		add_filter( 'go_xpost_process_post_' . $this->property, array( $this, 'go_xpost_get_post' ), 2 );
		add_filter( 'go_xpost_get_post_' . $this->property, array( $this, 'go_xpost_get_post' ), 2 );
	} // END __construct

	/**
	 * Filter whether a post_id should ping a property
	 * 
	 * @param  absint $post_id, string $target_property
	 * @return $post_id or FALSE
	 */
	public function go_xpost_process_post_gigaom( $post_id, $target_property )
	{
		if ( 'paidcontent' == $target_property )
		{
			// Get the post channels
			$channels = wp_get_object_terms( $post_id, 'channel' );

			// Check for media in the list of channels
			if ( ! in_array( 'media', $channels ) )
			{
				// If media isn't in the list we shouldn't push this to paidContent
				return FALSE;
			}
		} // END if
		
		return $post_id;
	} // END go_xpost_process_post_gigaom
	
	/**
	 * Filter the $post object before returning it to a property
	 * 
	 * @param  object $post, string $requesting_property
	 * @return $post
	 */
	public function go_xpost_get_post_gigaom( $post, $requesting_property )
	{
		return $post;
	} // END go_xpost_get_post_gigaom
} // END GO_XPost_Gigaom