<?php
/*
 * Filter Name: Enterprises -> Endpoint
 */
class GO_XPost_Filter_Enterprises extends GO_XPost_Filter
{
	/**
	 * Determine whether a post_id should ping a site
	 *
	 * @param  absint $post_id
	 * @return boolean
	 */
	public function should_send_post( $post_id )
	{
		$post = get_post( $post_id );

		// We only want condor-enterprise posts
		if ( 'condor-enterprise' != $post->post_type )
		{
			return FALSE;
		} // END if
		
		return TRUE;
	} // END should_send_post
} // END GO_XPost_Filter_Enterprises