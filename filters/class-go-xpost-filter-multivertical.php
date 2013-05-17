<?php

/*
Filter Name: Posts with multiple verticals -> Endpoint
*/

class GO_XPost_Filter_MultiVertical extends GO_XPost_Filter
{
	/**
	 * Determine whether a post_id should ping a property
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

		// If there is more than one vertical, then we want to XPost
		if ( count( $channels ) > 1 )
		{
			return TRUE;
		}

		return FALSE;
	} // END should_send_post
} // END GO_XPost_Filter_MultiVertical
