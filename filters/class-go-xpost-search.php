<?php

class GO_XPost_Filter_Search
{
	/**
	 * URL of the site that this filter will apply to
	 */
	public $endpoint_url;
	
	/**
	 * The shared secret that is configured for this endpoint
	 */
	public $endpoint_secret;

	public function __construct( $endpoint_url, $endpoint_secret )
	{
		$this->endpoint_url = $endpoint_url;
		$this->endpoint_secret = $endpoint_secret;
	} // END __construct

	/**
	 * Determine whether a post_id should ping a property
	 *
	 * @param  absint $post_id, string $target_property
	 * @return boolean
	 */
	public function should_send_post( $post_id, $target_property )
	{
		return TRUE;
	} // END should_send_post

} // END GO_XPost_Search