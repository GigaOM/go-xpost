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
	 * Alter the $xpost object before returning it to the endpoint
	 * Note: $xpost is NOT a WP_Post object, but it contains one in $xpost->post
	 *
	 * @param  object $xpost
	 * @param  int $post_id
	 * @return custom object containing WP_Post
	 */
	public function post_filter( $xpost, $post_id )
	{
		return $xpost;
	}// end post_filter
}// end GO_XPost_Filter