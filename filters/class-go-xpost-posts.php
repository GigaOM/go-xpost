<?php

/*
Filter Name: Posts -> Endpoint
*/

class GO_XPost_Filter_Posts
{
	public $endpoint = '';
	
	/**
	 * Determine whether a post_id should ping a property
	 *
	 * @param  absint $post_id
	 * @return boolean
	 */
	public function should_send_post( $post_id, $target_property )
	{
		// Only send posts
		if ( 'post' != get_post( $post_id )->post_type )
		{
			return FALSE;
		}// end if

		return TRUE;
	} // END should_send_post

	/**
	 * Alter the $post object before returning it to the endpoint
	 *
	 * @param  object $post
	 * @return $post WP_Post
	 */
	public function post_filter( $post )
	{
		return $post;
	}// end post_filter
} // END GO_XPost_Filter_Posts