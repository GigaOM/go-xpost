<?php

class GO_XPost_Filter_Paidcontent
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
	public function should_send_post( $post_id )
	{
		if ( 'pro' == $target_property && 'go-datamodule' != get_post( $post_id )->post_type )
		{
			// pC should only push charts to pro
			return FALSE;
		} // END if
		
		return TRUE;
	} // END should_process_post

} // END GO_XPost_Paidcontent