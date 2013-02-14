<?php

class GO_XPost_Filter_Search
{
	/**
	 * URL of the site that this filter will apply to
	 */
	public $endpoint_url;

	public function __construct( $endpoint_url )
	{
	} // END __construct

	/**
	 * Determine whether a post_id should ping a property
	 *
	 * @param  absint $post_id, string $target_property
	 * @return boolean
	 */
	public function should_process_post( $post_id, $target_property )
	{
		return TRUE;
	} // END should_process_post

} // END GO_XPost_Search