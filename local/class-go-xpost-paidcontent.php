<?php

class GO_XPost_Paidcontent extends GO_XPost
{
	public function __construct( $config )
	{
		parent::__construct( $config );

		add_filter( 'go_xpost_process_post_' . $this->property, array( $this, 'go_xpost_process_post_paidcontent' ), 10, 2 );
		add_filter( 'go_xpost_get_post_' . $this->property, array( $this, 'go_xpost_get_post_paidcontent' ), 10, 2 );
	} // END __construct

	/**
	 * Filter whether a post_id should ping a property
	 *
	 * @param  absint $post_id, string $target_property
	 * @return $post_id or FALSE
	 */
	public function go_xpost_process_post_paidcontent( $post_id, $target_property )
	{
		if ( 'pro' == $target_property && 'go-datamodule' != get_post( $post_id )->post_type )
		{
			// pC should only push charts to pro
			return FALSE;
		} // END if
		
		return $post_id;
	} // END go_xpost_process_post_paidcontent

	/**
	 * Filter the $post object before returning it to a property
	 *
	 * @param  object $post, string $requesting_property
	 * @return $post
	 */
	public function go_xpost_get_post_paidcontent( $post, $requesting_property )
	{
		return $post;
	} // END go_xpost_get_post_paidcontent
} // END GO_XPost_Paidcontent