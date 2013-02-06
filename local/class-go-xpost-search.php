<?php

class GO_XPost_Paidcontent extends GO_XPost
{
	public function __construct( $config )
	{
		GO_XPost::__construct( $config );

		add_filter( 'go_xpost_process_post_' . $this->property, array( $this, 'go_xpost_process_post_search' ), 10, 2 );
		add_filter( 'go_xpost_get_post_' . $this->property, array( $this, 'go_xpost_get_post_search' ), 10, 2 );
	} // END __construct

	/**
	 * Filter whether a post_id should ping a property
	 *
	 * @param  absint $post_id, string $target_property
	 * @return $post_id or FALSE
	 */
	public function go_xpost_process_post_search( $post_id, $target_property )
	{
		return $post_id;
	} // END go_xpost_process_post_search

	/**
	 * Filter the $post object before returning it to a property
	 *
	 * @param  object $post, string $requesting_property
	 * @return $post
	 */
	public function go_xpost_get_post_search( $post, $requesting_property )
	{
		return $post;
	} // END go_xpost_get_post_search
} // END GO_XPost_Paidcontent