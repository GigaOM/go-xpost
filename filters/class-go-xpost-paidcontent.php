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
		// Don't send any graphs to PC
		if ( 'go-datamodule' == get_post( $post_id )->post_type )
		{
			return FALSE;
		}// end if
		
		// Get the post channels
		$channels = wp_get_object_terms( $post_id, 'channel', array( 'fields' => 'slugs' ) );

		// Check for media in the list of channels
		if ( is_array( $channels ) && ! in_array( 'media', $channels ) )
		{
			// If media isn't in the list we shouldn't push this to paidContent
			return FALSE;
		}

		return TRUE;
	} // END should_send_post

} // END GO_XPost_Paidcontent