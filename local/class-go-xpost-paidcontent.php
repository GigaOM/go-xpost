<?php

class GO_XPost_Paidcontent extends GO_XPost
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
		if ( 'pro' == $target_property && 'go-datamodule' != get_post( $post_id )->post_type )
		{
			// pC should only push charts to pro
			return FALSE;
		} // END if
		
		return TRUE;
	} // END should_process_post

} // END GO_XPost_Paidcontent