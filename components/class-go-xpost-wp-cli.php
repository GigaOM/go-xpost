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

		fwrite( STDOUT, serialize( $post ) );
	} // END get_post

	/**
	 * Gets posts from the specified site and returns a serialized array of post objects.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : The url of the site you want posts from.
	 * [--path=<path>]
	 * : Path to WordPress files.
	 * [--query=<query>]
	 * : Query string suitable for WP get_posts method in quotes (i.e. --query="post_type=post&posts_per_page=5&offset=0").
	 *	
	 * ## EXAMPLES
	 *
	 * wp go_xpost get_posts --url=<url> --query="post_type=post&posts_per_page=5&offset=0"
	 *
	 * @synopsis [--url=<url>] [--path=<path>] [--query=<query>]
	 */
	function get_posts( $args, $assoc_args )
	{
		// Setup arguments for source query
		$query_args = wp_parse_args( isset( $assoc_args['query'] ) ? ' ' . $assoc_args['query'] : '' );

		$query_args['fields'] = 'ids';

		// Try to get posts
		$posts = get_posts( $query_args );

		if ( ! is_array( $posts ) || ! is_numeric( array_shift( $posts ) ) )
		{
			WP_CLI::error( 'Could not find any posts.' );
		} // END if

		$return = array();

		foreach ( $posts as $post_id )
		{
			// Get the post
			$post = go_xpost_util()->get_post( $post_id );

			if ( is_wp_error( $post ) )
			{
				if ( isset( $assoc_args['verbose'] ) )
				{
					$return['errors'][ $post_id ][] = $post->get_error_message();
				} // END if

				continue;
			} // END if

			$return[] = $post;

			// Copy sucessful
			$count++;
		} // END foreach

		fwrite( STDOUT, serialize( $return ) );
	} // END get_posts
	
	/**
	 * Save posts to a specified site from a file containing a serialized array of xPost post objects.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : The url of the site you want save posts to.
	 * [--path=<path>]
	 * : Path to WordPress files.
	 * <posts-file>
	 * : A file with a serialized array of xPost post objects.
	 *	
	 * ## EXAMPLES
	 *
	 * wp go_xpost get_posts --url=<url> <posts-file>
	 *
	 * @synopsis [--url=<url>] [--path=<path>] <posts-file>
	 */
	function save_posts( $args, $assoc_args )
	{
	    // Check if file exists
		$file = $args[0];
		
		if ( ! file_exists( $file ) )
		{
			WP_CLI::error( 'Posts file does not exist!' );
		} // END if

		$posts = unserialize( file_get_contents( $file ) ); // This is failing for some reason! BLARGH@#$%!
		
		if ( ! is_array( $posts ) || ! is_numeric( array_shift( $posts ) ) )
		{
			WP_CLI::error( 'Could not find any posts in the file.' );
		} // END if
		
		print_r($posts); exit();
		foreach ( $posts as $post_id )
		{
			// Get the post
			$post = go_xpost_util()->get_post( $post_id );

			if ( is_wp_error( $post ) )
			{
				if ( isset( $assoc_args['verbose'] ) )
				{
					WP_CLI::line( $post->get_error_message() );
				} // END if

				continue;
			} // END if

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
	} // END save_posts
} // END GO_XPost_WP_CLI

WP_CLI::add_command( 'go_xpost', 'GO_XPost_WP_CLI' );