<?php

/**
 * Gigaom xPost WP CLI logging functions
 */
class GO_XPost_WP_CLI_Logging
{
	public $log_file = FALSE;
	public $log_handle = FALSE; // log file handle

	/**
	 */
	public function __construct( $log_file )
	{
		$this->log_file = $log_file;

		// make sure the log file is writable by opening it in append,
		// write mode
		if ( $this->log_file )
		{
			$this->log_handle = fopen( $this->log_file, 'a' );
		}
	}//END __construct

	/**
	 * clean up any resources. in this case just close the log file handle.
	 */
	public function __destruct()
	{
		if ( FALSE !== $this->log_handle )
		{
			fclose( $this->log_handle );
		}
	}//END __destruct

	/**
	 * output a log entry for a get_post triggered by the get_posts
	 * command.
	 *
	 * @param $post_id int ID of the post to retrieve
	 * @param $post object retrieved post object. this is more than a
	 *  wp_post object and contains other metadata (post meta, terms, etc.)
	 *  if get_post() returned an error then this would be a WP_Error object.
	 * @query_args wp query argument used to retrieve this post
	 */
	public function log_get_posts_status( $post_id, $post, $query_args )
	{
		$log_entry = array(
			'get_posts',           // wp-cli command name
			date( DATE_ISO8601 ),  // timestamp
		);

		if ( is_wp_error( $post ) )
		{
			$log_entry[] = $query_args['post_type']; // post_type
			$log_entry[] = $post_id;                 // post id
			$log_entry[] = '';                       // permalink
			$log_entry[] = 'error:"' . $post->get_error_message() . '"'; // status
		}
		else
		{
			$log_entry[] = $post->post->post_type;   // post_type
			$log_entry[] = $post_id;                 // post id
			$log_entry[] = $post->origin->permalink; // permalink
			$log_entry[] = 'ok';                     // status
		}//END else

		if ( FALSE === fwrite( $this->log_handle, implode( ',', $log_entry ) . "\n" ) )
		{
			throw new Exception( 'Error writing to log file ' . $this->log_file );
		}

		// make sure we don't lose an entry if the system dies for some reason
		fflush( $this->log_handle );
	}//END log_get_posts_status


	/**
	 * log the status of a get_post command (not to be confused with the
	 * get_posts command)
	 *
	 * @param $post_id int ID of the post to retrieve
	 * @param $post object retrieved post object. this is more than a
	 *  wp_post object and contains other metadata (post meta, terms, etc.)
	 *  if get_post() returned an error then this would be a WP_Error object.
	 */
	public function log_get_post_status( $post_id, $post )
	{
		$log_entry = array(
			'get_post',            // wp-cli command name
			date( DATE_ISO8601 ),  // timestamp
		);

		if ( is_wp_error( $post ) )
		{
			$log_entry[] = 'unknown'; // post_type
			$log_entry[] = '';        // permalink
			$log_entry[] = 'error:"' . $post->get_error_message() . '"'; // status
		}
		else
		{
			$log_entry[] = $post->post->post_type;   // post_type
			$log_entry[] = $post->origin->permalink; // permalink
			$log_entry[] = 'ok';                     // status
		}//END else

		if ( FALSE === fwrite( $this->log_handle, implode( ',', $log_entry ) . "\n" ) )
		{
			throw new Exception( 'Error writing to log file ' . $this->log_file );
		}

		// make sure we don't lose an entry if the system dies for some reason
		fflush( $this->log_handle );
	}//END log_get_post_status

}//END GO_XPost_WP_CLI_Logging