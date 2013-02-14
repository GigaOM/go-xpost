<?php

class GO_XPost_Gigaom extends GO_XPost
{
	public function __construct( $config )
	{
		parent::__construct( $config );
	} // END __construct

	/**
	 * Determine whether a post_id should ping a property
	 *
	 * @param  absint $post_id, string $target_property
	 * @return boolean
	 */
	public function should_process_post( $post_id, $target_property )
	{
		if ( 'paidcontent' == $target_property && 'go-datamodule' != get_post( $post_id )->post_type )
		{
			// Get the post channels
			$channels = wp_get_object_terms( $post_id, 'channel', array( 'fields' => 'slugs' ) );

			// Check for media in the list of channels
			if ( ! in_array( 'media', $channels ) )
			{
				// If media isn't in the list we shouldn't push this to paidContent
				return FALSE;
			}
		} // END if
		else if ( 'pro' == $target_property && 'go-datamodule' != get_post( $post_id )->post_type )
		{
			// GO should only push charts to pro
			return FALSE;
		} // END if

		return TRUE;
	} // END should_process_post

} // END GO_XPost_Gigaom