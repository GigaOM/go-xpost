<?php

/**
 * Gigaom xPost WP CLI functions.
 */
class GO_XPost_WP_CLI extends WP_CLI_Command
{
	// define csv logging headings for each wp-cli command type
	public $csv_headings = array(
		'get_post' => array(
			'time',
			'command',
			'post_id',
			'post_type',
			'guid',
			'permalink',
			'status',
		),
		'get_posts' => array(
			'time',
			'command',
			'post_id',
			'post_type',
			'guid',
			'permalink',
			'status',
		),
		'save_posts' =>	array(
			'time',
			'command',
			'post_type',
			'guid',
			'parent_guid',
			'origin_id',
			'origin_permalink',
			'dest_id',
			'dest_permalink',
			'status',
		),
		'get_comments' => array(
			'time',
			'command',
			'post_id',
			'post_guid',
			'comment_id',
			'status',
		),
		'save_comments' => array(
			'time',
			'command',
			'post_guid',
			'origin_post_id',
			'origin_comment_id',
			'origin_comment_parent_id',
			'dest_post_id',
			'dest_comment_id',
			'dest_comment_parent_id',
			'status',
		),
	);
	public $csv = NULL;   // our Go_Csv logging object

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
	 * wp go_xpost get_post --logfile=/var/log/xpost.log <id>
	 *
	 * @synopsis --logfile=<logfile> <id>
	 */
	public function get_post( $args, $assoc_args )
	{
		$this->initialize_csv_log(
			$assoc_args,
			$this->csv_headings['get_post']
		);

		// Does this look like a post ID?
		if ( ! is_numeric( $args[0] ) )
		{
			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'get_post',
					'status' => 'error:invalid post id (' . $args[0] . ')',
				)
			);
			WP_CLI::error( 'That\'s not a post ID.' );
		} // END if

		// Try to get the post
		$post = go_xpost_util()->get_post( $args[0] );

		if ( is_wp_error( $post ) )
		{
			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'get_post',
					'post_id' => $args[0],
					'status' => 'error:' . $post->get_error_message(),
				)
			);
			WP_CLI::error( $post->get_error_message() );
		}//END if
		else
		{
			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'get_post',
					'post_id' => $args[0],
					'post_type' => $post->post->post_type,
					'guid' => $post->post->guid,
					'permalink' => $post->origin->permalink,
					'status' => 'ok',
				)
			);
		}//END else

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
	 * : Query string suitable for WP get_posts method in quotes (i.e. --query="post_type=post&posts_per_page=5&offset=0&order=DESC").
	 * [--include=<include-file>]
	 * : Path to an include file to further modify the data after getting the post.
	 * --logfile=<logfile>
	 * : where to log our results.
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost get_posts --url=pc.gigaom.com --query="post_type=post&posts_per_page=5&offset=0&orderby=ID&order=ASC"
	 *
	 * @synopsis [--url=<url>] [--path=<path>] [--query=<query>] [--include=<include-file>] --logfile=<logfile>
	 */
	public function get_posts( $args, $assoc_args )
	{
		$this->initialize_csv_log(
			$assoc_args,
			$this->csv_headings['get_posts']
		);

		// Is there an include file?
		if ( isset( $assoc_args['include'] ) )
		{
			if ( ! file_exists( $assoc_args['include'] ) )
			{
				$this->csv->log(
					array(
						'time' => date( DATE_ISO8601 ),
						'command' => 'get_posts',
						'status' => 'error:Include file (' . $assoc_args['include'] . ') doesn\'t exist.',
					)
				);
				WP_CLI::error( 'Include file doesn\'t exist.' );
			} // END if
		} // END if

		// Setup arguments for source query
		$query_args = wp_parse_args( isset( $assoc_args['query'] ) ? ' ' . $assoc_args['query'] : '' );

		$query_args['fields'] = 'ids';

		// Try to get posts
		$posts = get_posts( $query_args );

		if ( ! is_array( $posts ) || empty( $posts ) || ! is_numeric( $posts[0] ) )
		{
			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'get_posts',
					'status' => 'error:could not find any posts.',
				)
			);
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
				$post_type = $query_args['post_type'];
				$guid = NULL;
				$permalink = NULL;
				$status = 'error:' . $post->get_error_message();
			}
			else
			{
				$post_type = $post->post->post_type;
				$guid = $post->post->guid;
				$permalink = $post->origin->permalink;
				$status = 'ok';
			}

			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'get_posts',
					'post_id' => $post_id,
					'post_type' => $post_type,
					'guid' => $guid,
					'permalink' => $permalink,
					'status' => $status,
				)
			);

			if ( is_wp_error( $post ) )
			{
				$return['errors'][] = $post->get_error_message();
				continue;
			} // END if

			// Call include file if appropriate
			if ( isset( $assoc_args['include'] ) )
			{
				require $assoc_args['include'];
			} // END if

			// Copy sucessful
			$return[] = $post;
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
	 * [--include=<include-file>]
	 * : Path to an include file to further modify the data after saving the post.
	 * --logfile=<logfile>
	 * : where to log our results.
	 * [<posts-file>]
	 * : A file with a serialized array of xPost post objects.
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost save_posts --url=pc.gigaom.com --logfile=/var/log/xpost.log
	 *
	 * @synopsis [--url=<url>] [--path=<path>] [--include=<include-file>] --logfile=<logfile> [<posts-file>]
	 */
	public function save_posts( $args, $assoc_args )
	{
		$this->initialize_csv_log(
			$assoc_args,
			$this->csv_headings['save_posts']
		);

		// Is there an include file?
		if ( isset( $assoc_args['include'] ) )
		{
			if ( ! file_exists( $assoc_args['include'] ) )
			{
				$this->csv->log(
					array(
						'time' => date( DATE_ISO8601 ),
						'command' => 'get_posts',
						'status' => 'error:Include file (' . $assoc_args['include'] . ') doesn\'t exist.',
					)
				);
				WP_CLI::error( 'Include file doesn\'t exist.' );
			} // END if
		} // END if

		$file_content = $this->read_from_file_or_stdin( 'save_posts', $args );

		$posts = NULL;
		if ( ! empty( $file_content ) )
		{
			$posts = unserialize( $file_content );
		}

		if ( ! is_array( $posts ) || empty( $posts ) )
		{
			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'save_posts',
					'status' => 'error:Could not find any posts in the file ' . $file . '.',
				)
			);
			WP_CLI::error( 'Could not find any posts in the file ' . $file . '.' );
		} // END if

		// Check to see if any errors were found in the posts data
		if ( isset( $posts['errors'] ) && is_array( $posts['errors'] ) )
		{
			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'save_posts',
					'status' => 'warning:Errors were found in the posts data.',
				)
			);
			WP_CLI::warning( 'Errors were found in the posts data.' );

			foreach ( $posts['errors'] as $error )
			{
				$this->csv->log(
					array(
						'time' => date( DATE_ISO8601 ),
						'command' => 'save_posts',
						'status' => 'error:' . $error,
					)
				);
				WP_CLI::warning( $error );
			} // END foreach

			unset( $posts['errors'] );
		} // END if

		// Save the posts
		$found = count( $posts );
		$count = 0;

		foreach ( $posts as $post )
		{
			// Call include file if appropriate
			if ( isset( $assoc_args['include'] ) )
			{
				require $assoc_args['include'];
			} // END if

			// is this an attachment post?
			if ( 'attachment' == $post->post->post_type )
			{
				$post_id = go_xpost_util()->save_attachment( $post );
			}
			else
			{
				$post_id = go_xpost_util()->save_post( $post );
			}

			$is_wp_error = is_wp_error( $post_id );

			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'save_posts',
					'post_type' => $post->post->post_type,
					'guid' => $post->post->guid,
					'parent_guid' => isset( $post->parent ) ? $post->parent->guid : NULL,
					'origin_id' => $post->origin->ID,
					'origin_permalink' => $post->origin->permalink,
					'dest_id' => $is_wp_error ? NULL : $post_id,
					'dest_permalink' => $is_wp_error ? NULL : get_permalink( $post_id ),
					'status' => $is_wp_error ? $post_id->get_error_message() : 'ok',
				)
			);

			if ( $is_wp_error )
			{
				WP_CLI::warning( $post_id->get_error_message() );
				continue;
			} // END if

			WP_CLI::line( 'Copied: ' . $post->post->guid . ' -> ' . $post_id );

			// Copy sucessful
			$count++;
		} // END foreach

		if ( $count != $found )
		{
			WP_CLI::error( 'Copied ' . $count . ' post(s) of ' . $found . ' post(s) found!' );
		} // END if

		WP_CLI::success( 'Copied ' . $count . ' post(s) of ' . $found . ' post(s) found!' );
	} // END save_posts

	/**
	 * Gets comments from the specified site and returns a serialized array of comment objects.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : The url of the site you want comments from.
	 * [--path=<path>]
	 * : Path to WordPress files.
	 * [--query=<query>]
	 * : Query string suitable for WP get_comments method in quotes (i.e. --query="orderby=comment_date&order=ASC&number=5&offset=0").
	 * --logfile=<logfile>
	 * : where to log our results.
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost get_comments --url=<url> --query="orderby=comment_date&order=ASC&number=5&offset=0"
	 *
	 * @synopsis [--url=<url>] [--path=<path>] [--query=<query>] --logfile=<logfile>
	 */
	public function get_comments( $args, $assoc_args )
	{
		$this->initialize_csv_log(
			$assoc_args,
			$this->csv_headings['get_comments']
		);

		// Setup arguments for source query
		$query_args = wp_parse_args( isset( $assoc_args['query'] ) ? ' ' . $assoc_args['query'] : '' );

		// Try to get comments
		$comments = get_comments( $query_args );

		if ( ! is_array( $comments ) || empty( $comments ) || ! isset( $comments[0]->comment_ID ) || ! is_numeric( $comments[0]->comment_ID ) )
		{
			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'get_comments',
					'status' => 'Could not find any comments.',
				)
			);
			WP_CLI::error( 'Could not find any comments.' );
		} // END if

		$return = array();
		foreach ( $comments as $key => $comment )
		{
			$comment_obj = go_xpost_util()->get_comment( $comment->comment_ID );
			// did we get an error?
			if ( ! empty( $comment_obj->error ) )
			{
				$return['errors'][] = $comment_obj->error;
				$this->csv->log(
					array(
						'time' => date( DATE_ISO8601 ),
						'command' => 'get_comments',
						'comment_id' => $comment->comment_ID,
						'status' => $comment_obj->error,
					)
				);
				continue;
			}//END if

			$return[ $key ] = $comment_obj;

			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'get_comments',
					'post_id' => $comment_obj->post->ID,
					'post_guid' => $comment_obj->post->guid,
					'comment_id' => $comment_obj->comment->comment_ID,
					'status' => 'ok',
				)
			);
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
	 * : The url of the site you want save comments to.
	 * [--path=<path>]
	 * : Path to WordPress files.
	 * [<comments-file>]
	 * : A file with a serialized array of xPost comment objects.
	 * --logfile=<logfile>
	 * : where to log our results.
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost save_comments --url=<url> [<comments-file>]
	 *
	 * @synopsis [--url=<url>] [--path=<path>] [<comments-file>] --logfile=<logfile>
	 */
	public function save_comments( $args, $assoc_args )
	{
		$this->initialize_csv_log(
			$assoc_args,
			$this->csv_headings['save_comments']
		);

		$file_content = $this->read_from_file_or_stdin( 'save_comments', $args );
		$comments = NULL;
		if ( ! empty( $file_content ) )
		{
			$comments = unserialize( $file_content );
		}

		if ( ! is_array( $comments ) || empty( $comments ) )
		{
			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'save_comments',
					'status' => 'Could not find any comments in the input.',
				)
			);
			WP_CLI::error( 'Could not find any comments in the input.' );
		} // END if

		// Check to see if any errors were found in the comments data
		if ( isset( $comments['errors'] ) && is_array( $comments['errors'] ) )
		{
			WP_CLI::warning( 'Errors were found in the comments data.' );

			foreach ( $comments['errors'] as $error )
			{
				$this->csv->log(
					array(
						'time' => date( DATE_ISO8601 ),
						'command' => 'save_comments',
						'status' => $error,
					)
				);
				WP_CLI::warning( $error );
			} // END foreach

			unset( $comments['errors'] );
		} // END if

		// Save the comments
		$found = count( $comments );
		$count = 0;

		foreach ( $comments as $comment )
		{
			$source_comment_id = $comment->comment->comment_ID;

			$dest_comment = go_xpost_util()->save_comment( $comment );

			if ( is_wp_error( $dest_comment ) )
			{
				$this->csv->log(
					array(
						'time' => date( DATE_ISO8601 ),
						'command' => 'save_comments',
						'post_guid' => $comment->post->guid,
						'origin_post_id' => $comment->post->ID,
						'origin_comment_id' => $source_comment_id,
						'status' => $dest_comment->get_error_message(),
					)
				);
				WP_CLI::warning( $dest_comment->get_error_message() );
				continue;
			}//END if

			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'save_comments',
					'post_guid' => $comment->post->guid,
					'origin_post_id' => $comment->post->ID,
					'origin_comment_id' => $source_comment_id,
					'origin_comment_parent_id' => isset( $comment->parent ) ? $comment->parent->comment_ID : '',
					'dest_post_id' => $dest_comment->comment->comment_post_ID,
					'dest_comment_id' => $dest_comment->comment->comment_ID,
					'dest_comment_parent_id' => $dest_comment->comment->comment_parent,
					'status' => 'ok',
				)
			);
			WP_CLI::line( 'Copied: ' . $source_comment_id . ' -> ' . $dest_comment->comment->comment_ID );
			$count++;
		} // END foreach

		if ( $count != $found )
		{
			WP_CLI::error( 'Copied ' . $count . ' comments(s) of ' . $found . ' comments(s) found!' );
			// WP_CLI::error() terminates the wp-cli script
		} // END if

		WP_CLI::success( 'Copied ' . $count . ' comments(s) of ' . $found . ' comments(s) found!' );
	} // END save_comments

	/**
	 * get some input data either from a file argument or stdin.
	 *
	 * @param $command string which command is calling this function
	 * @param $args cml positional arguments
	 * @retval string file or stdin content if found.
	 */
	private function read_from_file_or_stdin( $command, $args )
	{
		// check if we have a file name param
		if ( 0 < count( $args ) )
		{
			$file = $args[0];

			if ( ! file_exists( $file ) )
			{
				$this->csv->log(
					array(
						'time' => date( DATE_ISO8601 ),
						'command' => $command,
						'status' => 'error:Input file ' . $file . ' does not exist!',
					)
				);
				WP_CLI::error( 'Input file ' . $file . ' does not exist!' );
			} // END if

			return file_get_contents( $file );
		} // END if

		// else try to read from STDIN
		$stdin_data = '';
		while ( ( $buffer = fgets( STDIN, 4096 ) ) !== FALSE )
		{
			$stdin_data .= $buffer;
		}

		return $stdin_data;
	}//END read_from_file_or_stdin

	/**
	 * initialize our csv logging object
	 */
	private function initialize_csv_log( $assoc_args, $columns )
	{
		if ( ! isset( $assoc_args['logfile'] ) )
		{
			WP_CLI::error( 'missing required "logfile" parameter.' );
		}

		$csv_file = fopen( $assoc_args['logfile'], 'a+' );
		if ( FALSE === $csv_file )
		{
			WP_CLI::error( 'Unable to open log file ' . $assoc_args['logfile'] );
		}

		$this->csv = new GO_Csv( $csv_file, $columns );
	}//END initialize_csv_log
}//END class GO_XPost_WP_CLI

WP_CLI::add_command( 'go_xpost', 'GO_XPost_WP_CLI' );


/**
 * originally from:
 * https://gigaom.unfuddle.com/a#/projects/7/repositories/18/file?path=%2Fmigration-scripts%2Ftrunk%2Fmigrator.php&commit=10758
 */
class Go_Csv
{
	private $output_file = NULL;
	private $columns = array();
	private $delimiter;
	private $enclosure;

	public function __construct( $output_file, $columns = null, $delimiter = ',', $enclosure = '"' )
	{
		if ( ! is_resource( $output_file ) )
		{
			WP_CLI::error( '$output_file must be a file resource.' );
		}

		$this->output_file = $output_file;
		if ( $columns )
		{
			$this->set_columns($columns);
		}
		$this->delimiter = (string) $delimiter;
		$this->enclosure = (string) $enclosure;
	}//END __construct

	/**
	 * log $data to the csv file. only values of keys that're in our
	 * columns array are written.
	 */
	public function log( $data )
	{
		$this->set_columns( array_keys( $data ) );

		$row = array_fill_keys( $this->columns, NULL );
		$data = array_intersect_key( $data, $row );
		$row = array_merge( $row, $data );
		if ( FALSE === fputcsv( $this->output_file, $row ) )
		{
			WP_CLI::error( 'error writing to logfile' );
		}
		fflush( $this->output_file );
	}//END log

	/**
	 * initilaizes the column headings if this hasn't been done yet,
	 * and sets up list of acceptable columnns.
	 */
	public function set_columns( $columns )
	{
		if ( empty( $this->columns ) && is_array( $columns ) )
		{
			$this->columns = $columns;
			if ( FALSE === fputcsv( $this->output_file, array_map( 'strval', $this->columns ) ) )
			{
				WP_CLI::error( 'error writing to logfile' );
			}
		}
	}//END set_columns
}//END Go_Csv
