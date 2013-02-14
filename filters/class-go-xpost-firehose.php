<?php

/**
 * Basic example filter that sends EVERYTHING to the defined endpoint
 *
 * the post_filter added here is entirely uneccessary, but establishes a generic example for others
 */

class GO_XPost_Filter_Firehose
{
	/**
	 * URL of the site that this filter will apply to
	 */
	public $endpoint_url;

	/**
	 * set the endpoint and 
	 *
	 * @param $endpoint string URL of the endpoint
	 */
	public function __construct( $endpoint_url )
	{
		$this->endpoint_url = $endpoint_url;

		add_filter( 'go_xpost_post_filter', array( $this, 'post_filter' ) );
	}// end __construct

	/**
	 * Determine whether a post_id should ping a property
	 *
	 * @param  absint $post_id, string $target_property
	 * @return boolean
	 */
	public function should_send_post( $post_id )
	{
		return TRUE;
	}// end should_send_post

	/**
	 * Alter the $post object before returning it to the endpoint
	 *
	 * @param  object $post, string $requesting_property
	 * @return $post WP_Post
	 */
	public function post_filter( $post, $requesting_property )
	{
		return $post;
	}// end post_filter

}// end GO_XPost_Filter_Firehose