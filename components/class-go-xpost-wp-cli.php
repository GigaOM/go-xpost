<?php

/**
 * Gigaom xPost WP CLI functions.
 */
class GO_XPost_WP_CLI extends WP_CLI_Command
{
	/**
	 * Returns a serialized post object using the Gigaom xPost get_post method.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the post to get.
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost get_post <id>
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

		echo serialize( $post );
	} // END get_post

	/**
	 * Copies posts from one site to another using Gigaom xPost methods
	 *
	 * ## OPTIONS
	 *
	 * --url=<url>
	 * : The url of the site you want posts copied to.
	 * --source=<url>
	 * : The url of the site you want posts copied from.
	 * [--path=<path>]
	 * : Path to WordPress files.
	 * [--source_path=<path>]
	 * : Output extra info as posts are copied.
	 * [--filter=<filter>]
	 * : Path to source WordPress files (if different).
	 * [--verbose]
	 * : Filter to run on the post data before it's copied
	 * [--query=<query>]
	 * : Query string suitable for wp-cli post list method in quotes (i.e. --query="--post_type=post --posts_per_page=5 --offset=0")
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost copy_posts --url=<url> --source=<url> --query="--post_type=post --posts_per_page=5 --offset=0"
	 *
	 * @synopsis --url=<url> --source=<url> [--path=<path>] [--source_path=<path>] [--filter=<filter>] [--verbose] [--query=<query>]
	 */
	function copy_posts( $args, $assoc_args )
	{
		// Setup arguments for source query
		$query  = isset( $assoc_args['query'] ) ? ' ' . $assoc_args['query'] : '';
		$filter = isset( $assoc_args['filter'] ) ? $assoc_args['filter'] : 'default';
		$filter = dirname( __DIR__ ) . '/wp-cli-filters/' . $filter . '.php';

		// Check if filter exists
		if ( ! file_exists( $filter ) )
		{
			WP_CLI::error( 'Filter doesn\'t exist.' );
		} // END if

		if ( isset( $assoc_args['query'] ) )
		{
			$query = ' ' . $assoc_args['query'];
		} // END if

		$source_args = array();
		$source_args['url'] = $assoc_args['source'];

		if ( isset( $assoc_args['source_path'] ) )
		{
			$source_args['path'] = $assoc_args['source_path'];
		} // END if

		// Create source string (url/path) for the source commands
		$source_string = '';

		foreach ( $source_args as $arg => $value )
		{
			$source_string .= ' --' . $arg . '=' . $value;
		} // END foreach

		$posts = json_decode( exec( 'wp post list --field=ID --format=json' . $source_string . $query ) );

		if ( ! is_array( $posts ) || ! is_numeric( array_shift( $posts ) ) )
		{
			WP_CLI::error( 'Could not find any posts.' );
		} // END if

		$count = 0;
		$found = count( $posts );

		foreach ( $posts as $post_id )
		{
			// Try to get the post
			$post = unserialize( exec( 'wp go_xpost get_post ' . $post_id . $source_string ) );

			if ( is_wp_error( $post ) )
			{
				if ( isset( $assoc_args['verbose'] ) )
				{
					WP_CLI::line( $post->get_error_message() );
				} // END if

				continue;
			} // END if

			// Load filter
			require $filter;

			// Save the post!
			$new_post_id = go_xpost_util()->save_post( $post );

			if ( is_wp_error( $new_post_id ) )
			{
				if ( isset( $assoc_args['verbose'] ) )
				{
					WP_CLI::line( 'Warning: ' . $new_post_id->get_error_message() );
				} // END if
			} // END if
			elseif ( isset( $assoc_args['verbose'] ) )
			{
				WP_CLI::line( 'Copied: ' . $post_id . ' => ' . $new_post_id );
			} // END elseif

			// Copy sucessful
			$count++;
		} // END foreach

		WP_CLI::success( 'Copied ' . $count . ' post(s) of ' . $found . ' post(s) found!' );
	} // END copy_posts
} // END GO_XPost_WP_CLI

WP_CLI::add_command( 'go_xpost', 'GO_XPost_WP_CLI' );