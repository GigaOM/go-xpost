<?php

/*
Filter Name: Posts -> Endpoint
*/

class GO_XPost_Filter_Posts extends GO_XPost_Filter
{
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
} // END GO_XPost_Filter_Posts