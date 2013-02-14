<?php

class GO_XPost_Filter_Gigaom
{
	/**
	 * URL of the site that this filter will apply to
	 */
	public $endpoint_url;

	/**
	 * The shared secret that is configured for this endpoint
	 */
	public $endpoint_secret;

	public function __construct( $endpoint )
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
		// Don't send any graphs to GigaOM
		if ( 'go-datamodule' == get_post( $post_id)->post_type )
		{
			return FALSE;
		} // END if

		return TRUE;
	} // END should_send_post

} // END GO_XPost_Gigaom