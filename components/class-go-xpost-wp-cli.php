<?php

/**
 * Gigaom xPost WP CLI functions.
 */
class GO_XPost_WP_CLI extends WP_CLI_Command
{
	public $comment_posts = array();

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

		// Make sure non-blocking STDIN will get this
		fflush( STDOUT );
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

		if ( ! is_array( $posts ) || empty( $posts ) || ! is_numeric( $posts[0] ) )
		{
			WP_CLI::error( 'Could not find any posts.' );
		} // END if

		$return = array();
		$count  = 0;
		$is_attachment = isset( $query_args['post_type'] ) && ( 'attachment' == $query_args['post_type'] );

		foreach ( $posts as $post_id )
		{
			// Get the post
			if ( $is_attachment )
			{
				$post = go_xpost_util()->get_attachment( $post_id );
			}
			else
			{
				$post = go_xpost_util()->get_post( $post_id );
			}

			if ( is_wp_error( $post ) )
			{
				$return['errors'][ $post_id ][] = $post->get_error_message();
				continue;
			} // END if

			// Get post comments
			$post->comments = go_xpost_util()->get_post_comments( $post_id );

			$return[] = $post;

			// Copy sucessful
			$count++;
		} // END foreach

		fwrite( STDOUT, serialize( $return ) );

		// Make sure non-blocking STDIN will get this
		fflush( STDOUT );
	} // END get_posts

	/**
	 * Save posts to a specified site from a file or STDIN containing a serialized array of xPost post objects.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : The url of the site you want save posts to.
	 * [--path=<path>]
	 * : Path to WordPress files.
	 * [<posts-file>]
	 * : A file with a serialized array of xPost post objects.
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost save_posts --url=<url> [<posts-file>]
	 *
	 * @synopsis [--url=<url>] [--path=<path>] [<posts-file>]
	 */
	function save_posts( $args, $assoc_args )
	{
		$file_content = '';

		// Are we reading from STDIN or a file arg?
		// To test this we make reading from STDIN non-blocking since we only expect piped in content, not user input.
		// Then we use stream_select() to check if there's any thing to read, with a half second max timeout to give get_posts() some time to write.
		stream_set_blocking( STDIN, 0 );
		$rstreams = array( STDIN );
		$wstreams = NULL;
		$estreams = NULL;

		$num_input_read = stream_select( $rstreams, $wstreams, $estreams, 0, 500000 );

		if ( ( FALSE !== $num_input_read ) && ( 0 < $num_input_read ) )
		{
			while ( ( $buffer = fgets( STDIN, 4096 ) ) !== FALSE )
			{
				$file_content .= $buffer;
			}
		}//END if

		// If we didn't get anything from STDIN then check if we got a filename in $args
		if ( empty( $file_content ) && ( 0 < count( $args ) ) )
		{
			$file = $args[0];

			if ( ! file_exists( $file ) )
			{
				WP_CLI::error( 'Posts file does not exist!' );
			} // END if

			$file_content = file_get_contents( $file );
		} // END if

		$posts = NULL;

		if ( ! empty( $file_content ) )
		{
			$posts = unserialize( $file_content );
		}

		if ( ! is_array( $posts ) || empty( $posts ) )
		{
			WP_CLI::error( 'Could not find any posts in the file.' );
		} // END if

		// Check to see if any errors were found in the posts data
		if ( isset( $posts['errors'] ) && is_array( $posts['errors'] ) )
		{
			WP_CLI::warning( 'Errors were found in the posts data.' );

			foreach ( $posts['errors'] as $error )
			{
				WP_CLI::warning( $error );
			} // END foreach

			unset( $posts['errors'] );
		} // END if

		// Save the posts
		$found = count( $posts );
		$count = 0;

		foreach ( $posts as $post )
		{
			// is this an attachment post?
			if ( 'attachment' == $post->post->post_type )
			{
				$post_id = go_xpost_util()->save_attachment( $post );
			}
			else
			{
				$post_id = go_xpost_util()->save_post( $post );
			}

			if ( is_wp_error( $post_id ) )
			{
				WP_CLI::warning( $post_id->get_error_message() );
				continue;
			} // END if

			WP_CLI::line( 'Copied: ' . $post->post->guid . ' -> ' . $post_id );

			// Copy sucessful
			$count++;
		} // END foreach

		WP_CLI::success( 'Copied ' . $count . ' post(s) of ' . $found . ' post(s) found!' );
	} // END save_posts

	/**
	 * Gets comments from the specified site and returns a serialized array of comment objects.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : The url of the site you want posts from.
	 * [--path=<path>]
	 * : Path to WordPress files.
	 * [--query=<query>]
	 * : Query string suitable for WP get_comments method in quotes (i.e. --query="orderby=comment_date&order=ASC&number=5&offset=0").
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost get_comments --url=<url> --query="orderby=comment_date&order=ASC&number=5&offset=0"
	 *
	 * @synopsis [--url=<url>] [--path=<path>] [--query=<query>]
	 */
	function get_comments( $args, $assoc_args )
	{
		// Setup arguments for source query
		$query_args = wp_parse_args( isset( $assoc_args['query'] ) ? ' ' . $assoc_args['query'] : '' );

		// Try to get comments
		$comments = get_comments( $query_args );

		if ( ! is_array( $comments ) || empty( $comments ) || ! isset( $comments[0]->comment_ID ) || ! is_numeric( $comments[0]->comment_ID ) )
		{
			WP_CLI::error( 'Could not find any comments.' );
		} // END if

		$return = array();
		$count  = 0;

		foreach ( $comments as $key => $comment )
		{
			// Get the post object related to this comment
			if ( isset( $this->comment_posts[ $comment->comment_post_ID ] ) )
			{
				$post = $this->comment_posts[ $comment->comment_post_ID ];
			} // END if
			else
			{
				$post = get_post( $comment->comment_post_ID );

				// Save this in case any other comments are for the same post
				$this->comment_posts[ $comment->comment_post_ID ] = $post;
			} // END else

			if ( ! $post )
			{
				$return['errors'][ $comment->comment_ID ][] = 'Could not get the post associated with the comment (POST ID: ' . $comment->comment_post_ID . ' Comment ID: ' . $comment->comment_ID . ')';
				continue;
			} // END if

			$return[ $key ]->comment = $comment;
			$return[ $key ]->post    = $post;

			// Get comment meta
			$comment_meta = get_comment_meta( $comment->comment_ID );

			if ( ! empty( $comment_meta ) )
			{
				foreach ( $comment_meta as $mkey => $mval )
				{
					$return[ $key ]->meta[ $mkey ] = maybe_unserialize( $mval[0] );
				} // END foreach
			} // END if

			// Get parent
			if( $comment->comment_parent )
			{
				if ( $parent = get_comment( $comment->comment_parent ) )
				{
					$return[ $key ]->parent = $parent;
				} // END if
				else
				{
					$return['errors'][ $comment->comment_ID ][] = 'Could not get the parent associated with the comment (PARENT ID: ' . $comment->comment_parent . ' Comment ID: ' . $comment->comment_ID . ')';
				} // END else
			}
		} // END foreach

		fwrite( STDOUT, serialize( $return ) );

		// Make sure non-blocking STDIN will get this
		fflush( STDOUT );
	} // END get_comments

	/**
	 * Save comments to a specified site from a file or STDIN containing a serialized array of xPost comment objects.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : The url of the site you want save posts to.
	 * [--path=<path>]
	 * : Path to WordPress files.
	 * [<comments-file>]
	 * : A file with a serialized array of xPost comment objects.
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost save_comments --url=<url> [<comments-file>]
	 *
	 * @synopsis [--url=<url>] [--path=<path>] [<comments-file>]
	 */
	function save_comments( $args, $assoc_args )
	{
		$file_content = '';

		// Are we reading from STDIN or a file arg?
		// To test this we make reading from STDIN non-blocking since we only expect piped in content, not user input.
		// Then we use stream_select() to check if there's any thing to read, with a half second max timeout to give get_posts() some time to write.
		stream_set_blocking( STDIN, 0 );
		$rstreams = array( STDIN );
		$wstreams = NULL;
		$estreams = NULL;

		$num_input_read = stream_select( $rstreams, $wstreams, $estreams, 0, 500000 );

		if ( ( FALSE !== $num_input_read ) && ( 0 < $num_input_read ) )
		{
			while ( ( $buffer = fgets( STDIN, 4096 ) ) !== FALSE )
			{
				$file_content .= $buffer;
			}
		}//END if

		// If we didn't get anything from STDIN then check if we got a filename in $args
		if ( empty( $file_content ) && ( 0 < count( $args ) ) )
		{
			$file = $args[0];

			if ( ! file_exists( $file ) )
			{
				WP_CLI::error( 'Comments file does not exist!' );
			} // END if

			$file_content = file_get_contents( $file );
		} // END if

		$comments = NULL;

		if ( ! empty( $file_content ) )
		{
			$comments = unserialize( $file_content );
		}

		if ( ! is_array( $comments ) || empty( $comments ) )
		{
			WP_CLI::error( 'Could not find any posts in the file.' );
		} // END if

		// Check to see if any errors were found in the posts data
		if ( isset( $comments['errors'] ) && is_array( $comments['errors'] ) )
		{
			WP_CLI::warning( 'Errors were found in the comments data.' );

			foreach ( $comments['errors'] as $error )
			{
				WP_CLI::warning( $error );
			} // END foreach

			unset( $comments['errors'] );
		} // END if

		// Save the posts
		$found = count( $comments );
		$count = 0;

		foreach ( $comments as $comment )
		{
			// Keep this for output purposes
			$old_comment_id = $comment->comment->comment_ID;
			unset( $comment->comment->comment_ID );

			// Does the comment's post exist?
			if ( ! $post_id = go_xpost_util()->post_exists( $comment->post ) )
			{
				WP_CLI::warning( 'Comment post could not be found (GUID: ' . $comment->post->guid . ')' );
				continue;
			} // END if

			// Does the comment's parent exist?
			if ( isset( $comment->parent ) && $parent_id = go_xpost_util()->comment_exists( $comment->parent ) )
			{
				WP_CLI::warning( 'Comment parent could not be found (PARENT ID: ' . $comment->parent->comment_ID . ')' );
				continue;
			} // END if

			// Check if comment already exists
			if ( $comment_id = go_xpost_util()->comment_exists( $comment ) )
			{
				$comment->comment->comment_ID = $comment_id;
				wp_update_comment( $comment->comment );
			} // END if
			else
			{
				$comment_id = wp_insert_comment( $comment->comment );
			} // END else

			// Is there comment meta?
			if ( isset( $comment->meta ) && is_array( $comment->meta ) )
			{
				foreach ( $comment->meta as $meta_key => $meta_value )
				{
					delete_comment_meta( $comment_id, $meta_key );
					add_comment_meta( $comment_id, $meta_key, $meta_value );
				} // END foreach
			} // END if

			WP_CLI::line( 'Copied: ' . $old_comment_id . ' -> ' . $comment_id );

			// Copy sucessful
			$count++;
		} // END foreach

		WP_CLI::success( 'Copied ' . $count . ' comment(s) of ' . $found . ' comment(s) found!' );
	} // END save_comments
} // END GO_XPost_WP_CLI

WP_CLI::add_command( 'go_xpost', 'GO_XPost_WP_CLI' );