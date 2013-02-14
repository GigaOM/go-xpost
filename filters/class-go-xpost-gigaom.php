<?php

class GO_XPost_Filter_Gigaom
{
	/**
	 * URL of the site that this filter will apply to
	 */
	public $endpoint_url;

	public function __construct( $endpoint )
	{
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