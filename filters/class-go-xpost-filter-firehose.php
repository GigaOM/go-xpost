<?php

/*
Filter Name: Everything -> Endpoint
*/

/**
 * Basic example filter that sends EVERYTHING to the defined endpoint
 */

class GO_XPost_Filter_Firehose extends GO_XPost_Filter
{
	/**
	 * Determine whether a post_id should ping a site
	 *
	 * @param  absint $post_id
	 * @return boolean
	 */
	public function should_send_post( $post_id )
	{
		return TRUE;
	}// end should_send_post

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
}// end GO_XPost_Filter_Firehose