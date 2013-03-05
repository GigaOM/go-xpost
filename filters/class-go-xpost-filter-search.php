<?php

/*
Filter Name: Posts Appropriate For search.gigaom.com -> Endpoint
*/

class GO_XPost_Filter_Search extends GO_XPost_Filter
{
	/**
	 * Determine whether a post_id should ping a site
	 *
	 * @param  absint $post_id
	 * @return boolean
	 */
	public function should_send_post( $post_id )
	{
		$valid_post_types = array(
			'go_shortpost',
			'go-report',
			'go-report-section',
			'go-datamodule',
			'go_webinar',
			'post',
		);
		
		if ( in_array( get_post( $post_id )->post_type, $valid_post_types ) )
		{
			return TRUE;
		} // END if

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
		$post->terms['go-property'][0] = go_config()->get_config_name();

		return $post;
	}// end post_filter
} // END GO_XPost_Filter_Search