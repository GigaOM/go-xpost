<?php

/*
Filter Name: Posts in Media Channel -> Endpoint
*/

class GO_XPost_Filter_Media
{
	public $endpoint = '';
	
	/**
	 * Determine whether a post_id should ping a site
	 *
	 * @param  absint $post_id
	 * @return boolean
	 */
	public function should_send_post( $post_id )
	{
		// Only send posts
		if ( 'post' != get_post( $post_id )->post_type )
		{
			return FALSE;
		}// end if

		// Get the post channels
		$channels = wp_get_object_terms( $post_id, 'channel', array( 'fields' => 'slugs' ) );

		// Check for media in the list of channels
		if ( is_array( $channels ) && in_array( 'media', $channels ) )
		{
			// If media is in the list we can push this to paidContent
			return TRUE;
		}

		return FALSE;
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
} // END GO_XPost_Filter_Media