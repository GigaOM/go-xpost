<?php

/*
Filter Name: Posts in Media Channel -> Endpoint
*/

class GO_XPost_Filter_Media extends GO_XPost_Filter
{
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
		$channels = wp_list_pluck( get_the_terms( $post_id, 'channel' ), 'slug' );

		// Check for media in the list of channels
		if ( is_array( $channels ) && in_array( 'media', $channels ) )
		{
			// If media is in the list we can push this to paidContent
			return TRUE;
		}

		return FALSE;
	} // END should_send_post
}// END GO_XPost_Filter_Media
