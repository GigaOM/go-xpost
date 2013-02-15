<?php

/**
 * Abstract class that all other filters extend
 */

abstract class GO_XPost_Filter
{
	public $endpoint = '';
	
	/**
	 * Determine whether a post_id should ping a site
	 *
	 * @param  absint $post_id
	 * @return boolean
	 */
	abstract protected function should_send_post( $post_id );

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
}// end GO_XPost_Filter