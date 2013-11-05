<?php

/**
 * Gigaom xPost WP CLI functions.
 */
class GO_XPost_WP_CLI extends WP_CLI_Command
{
	public $verbose = FALSE;

	/**
	 * Returns a post in JSON using the Gigaom xPost get_post method.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the post to get.
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost get_post <id> --verbose
	 *
	 * @synopsis <id>
	 */
	function get_post( $args, $assoc_args )
	{		
		// Does this look like a post ID?
		if ( ! is_numeric( $args[0] ) )
		{
			WP_CLI::error( 'That\'s not a post ID.' );
		} // END if
		
		// Try to get the post
		$post = go_xpost_util()->get_post( $args[0] );
		
		if ( is_wp_error( $post ) )
		{
			WP_CLI::error( $post->get_error_message() );
		} // END if
	
		echo json_encode( $post );
	} // END get_post
} // END GO_XPost_WP_CLI

WP_CLI::add_command( 'go_xpost', 'GO_XPost_WP_CLI' );