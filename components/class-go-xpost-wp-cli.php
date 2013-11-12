<?php

/**
 * Gigaom xPost WP CLI functions.
 */
class GO_XPost_WP_CLI extends WP_CLI_Command
{
	public $csv = NULL;   // our Go_Csv logging object

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
	);

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
	 * : Query string suitable for WP get_posts method in quotes (i.e. --query="post_type=post&posts_per_page=5&offset=0").
	 * --logfile=<logfile>
	 * : where to log our results.
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost get_posts --url=pc.gigaom.com --query="post_type=post&posts_per_page=5&offset=0"
	 *
	 * @synopsis [--url=<url>] [--path=<path>] [--query=<query>] --logfile=<logfile>
	 */
	public function get_posts( $args, $assoc_args )
	{
		$this->initialize_csv_log(
			$assoc_args,
			$this->csv_headings['get_posts']
		);

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
				$return['errors'][ $post_id ][] = $post->get_error_message();
				continue;
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
	 * --logfile=<logfile>
	 * : where to log our results.
	 * [<posts-file>]
	 * : A file with a serialized array of xPost post objects.
	 *
	 * ## EXAMPLES
	 *
	 * wp go_xpost save_posts --url=pc.gigaom.com --logfile=/var/log/xpost.log
	 *
	 * @synopsis [--url=<url>] [--path=<path>] --logfile=<logfile> [<posts-file>]
	 */
	public function save_posts( $args, $assoc_args )
	{
		$this->initialize_csv_log(
			$assoc_args,
			$this->csv_headings['save_posts']
		);

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
			// if we have anything from STDIN, set stream to blocking again
			// so we won't miss slow data flow from STDIN
			stream_set_blocking( STDIN, 1 );
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
				$this->csv->log(
					array(
						'time' => date( DATE_ISO8601 ),
						'command' => 'save_posts',
						'status' => 'error:Posts file ' . $file . ' does not exist!',
					)
				);
				WP_CLI::error( 'Posts file ' . $file . ' does not exist!' );
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
			WP_CLI::line( 'Warning: Errors were found in the posts data.' );

			foreach ( $posts['errors'] as $error )
			{
				$this->csv->log(
					array(
						'time' => date( DATE_ISO8601 ),
						'command' => 'save_posts',
						'status' => 'error:' . $error,
					)
				);
				WP_CLI::line( $error );
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

			$is_wp_error = is_wp_error( $post_id );
			$this->csv->log(
				array(
					'time' => date( DATE_ISO8601 ),
					'command' => 'save_posts',
					'post_type' => $post->post->post_type,
					'guid' => $post->post->guid,
					'parent_guid' => $post->parent ? $post->parent->guid : NULL,
					'origin_id' => $post->origin->ID,
					'origin_permalink' => $post->origin->permalink,
					'dest_id' => $is_wp_error ? NULL : $post_id,
					'dest_permalink' => $is_wp_error ? NULL : get_permalink( $post_id ),
					'status' => $is_wp_error ? $post_id->get_error_message() : 'ok',
				)
			);

			if ( $is_wp_error )
			{
				WP_CLI::line( 'Warning: ' . $post_id->get_error_message() );
				continue;
			} // END if

			WP_CLI::line( 'Copied: ' . $post->post->guid . ' -> ' . $post_id );

			// Copy sucessful
			$count++;
		} // END foreach

		WP_CLI::success( 'Copied ' . $count . ' post(s) of ' . $found . ' post(s) found!' );
	} // END save_posts

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
} // END GO_XPost_WP_CLI

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
