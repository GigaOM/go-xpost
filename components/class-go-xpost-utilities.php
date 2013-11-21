<?php

/**
 * Provides base functionality for cross posting,
 * could be leveraged via command line in addition to the typical implementation of GO_XPost
 */

class GO_XPost_Utilities
{
	private $pinged = array();

	// comment post cache: key = post ID, val = post object
	public $comment_posts = array();

	/**
	 * Ends the HTTP connection cleanly
	 */
	private function end_http_connection( $content = FALSE )
	{
		// suppressing errors from output buffering because this is precautionary, and will throw a notice in many cases.
		@ob_end_clean();
		header('Connection: close');
		FALSE == $content ? header("Content-Encoding: none\r\n") : header("Content-Encoding: utf-8\r\n");

		// buffer all upcoming output
		@ob_start();

		if ( $content == FALSE )
		{
			echo TRUE;
		} // END if
		else
		{
			echo $content;
		} // END else

		// get the size of the output
		$size = ob_get_length();

		// send headers to tell the browser to close the connection
		header('Content-Length: ' . $size);

		// flush all output
		ob_end_flush();
		flush();

		// close current session
		if ( session_id() )
		{
			session_write_close();
		}//end if
	}//end end_http_connection

	/**
	 * Get attachment, helps map the thumbnail post ID to post/guid
	 *
	 * @param $post_id int wordpress $post_id to get attachment
	 *
	 * @return attachment $r
	 */
	public function get_attachment( $post_id )
	{
		$post_id = (int) $post_id;

		// confirm that the requested post exists
		if ( ! get_post( $post_id ) )
		{
			return $this->error( 'go-xpost-failed-to-get-attachment', 'Failed to get the requested attachment (ID: ' . $post_id . ')', $post_id );
		}//end if

		// get the post
		$r = new StdClass();
		$r->post = clone get_post( $post_id );

		if ( is_wp_error( $r->post ) )
		{
			return $this->error( 'go-xpost-failed-to-get-attachment', 'Failed to get the requested attachment (ID: ' . $post_id . ')', $this->post_log_data( $r->post ) );
		}//end if

		// unset the post ID in the post object now to prevent risk of overwriting a post in another blog
		unset( $r->post->ID );

		// get the postmeta
		foreach ( (array) get_metadata( 'post', $post_id ) as $mkey => $mval )
		{
			$r->meta[ $mkey ] = maybe_unserialize( $mval[0] );
		}//end foreach

		// get file paths and URLs to the attached file
		$r->file = new StdClass();
		$r->file->url = wp_get_attachment_url( $post_id );

		// get the terms
		foreach ( (array) wp_get_object_terms( $post_id, get_object_taxonomies( $r->post->post_type ) ) as $term )
		{
			$r->terms[ $term->taxonomy ][] = $term->name;
		}//end foreach

		$r->origin = new StdClass();
		$r->origin->ID = $post_id;
		$r->origin->permalink = $r->file->url;

		// unset the attachment meta that needs to be regenerated on the remote site
		unset( $r->meta['_wp_attachment_metadata'] );
		unset( $r->meta['_wp_attached_file'] );

		return $r;
	}//end get_attachment

	/**
	 * Get post
	 *
	 * @param $post_id int wordpress $post_id to get attachment
	 *
	 * @return apply_filters: The type of return should be the same as the type of $r
	 */
	public function get_post( $post_id )
	{
		// check the post_id
		$post_id = $this->sanitize_post_id( $post_id );

		// confirm that the requested post exists
		if ( ! get_post( $post_id ))
		{
			return $this->error( 'go-xpost-failed-to-get-post', 'Failed to get the requested post (ID: ' . $post_id . ')', $r->post );
		}//end if

		//Should fix the 'Creating default object from empty value' error
		if ( ! isset( $r ) )
		{
			$r = new stdClass;
		}

		// get the post
		$r->post = clone get_post( $post_id );

		if ( is_wp_error( $r->post ) )
		{
			return $this->error( 'go-xpost-failed-to-get-post', 'Failed to get the requested post (ID: ' . $post_id . ')', $r->post );
		}//end if

		// unset the post ID in the post object now to prevent risk of overwriting a post in another blog
		unset( $r->post->ID );

		// get the postmeta
		foreach ( (array) get_metadata( 'post', $post_id ) as $mkey => $mval )
		{
			$r->meta[ $mkey ] = maybe_unserialize( $mval[0] );
		}//end foreach

		// get the terms
		foreach( (array) wp_get_object_terms( $post_id, get_object_taxonomies( $r->post->post_type ) ) as $term )
		{
			$r->terms[ $term->taxonomy ][] = $term->name;
		}//end foreach

		if ( $r->post->post_parent )
		{
			$r->parent = get_post( $r->post->post_parent );
		}//end if


		// map the thumbnail post ID to post/guid
		// this is compatible with the http://wordpress.org/extend/plugins/multiple-post-thumbnails/ plugin available on VIP
		foreach ( (array) $r->meta as $mkey => $mval )
		{
			if ( ( strpos( $mkey, '_thumbnail_id' ) !== FALSE ) && ( $attachment = $this->get_attachment( $mval ) ) && ! is_wp_error( $attachment ) )
			{
				$r->$mkey = $attachment;
			}//end if
		}//end foreach

		//Should fix the 'Creating default object from empty value' error
		if ( ! isset( $r ) )
		{
			$r = new stdClass;
		}
		// Get author data
		$r->author = get_userdata( $r->post->post_author );

		$r->origin = new StdClass;
		$r->origin->ID = $post_id;
		$r->origin->permalink = get_permalink( $post_id );

		// add meta to identify this as a xpost and link to the original
		$r->meta[ go_xpost_redirect()->meta_key ] = $r->origin->permalink;

		// Record comment count to a meta value so we can filter it on the receiving end
		$r->meta['go_xpost_comment_count'] = $r->post->comment_count;

		// unset the meta that we don't want to attempt to copy
		unset( $r->meta['_edit_lock'] );
		unset( $r->meta['_edit_last'] );

		// @TODO: these are Gigaom-specific meta keys, perhaps we should remove these in the go_xpost_post_filter?
		unset( $r->meta['_go_comment_cache'] );
		unset( $r->meta['_go_comment_cache_full'] );
		unset( $r->meta['go_oc_settings'] );
		unset( $r->meta['oc_commit_id'] );
		unset( $r->meta['oc_metadata'] );
		unset( $r->meta['_go_log'] );

		return apply_filters( 'go_xpost_post_filter', $r );
	}//end get_post

	/**
	 * Check post exists
	 *
	 * @param $post wp_postobject
	 *
	 * @return $post_id int
	 */
	public function post_exists( $post )
	{
		global $wpdb;

		$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . $wpdb->posts . ' WHERE guid = %s', trim( $post->guid ) ) );

		return $post_id;
	}//end post_exists
	
	/**
	 * Check if comment exists
	 *
	 * @param $comment wp comment object
	 *
	 * @return $comment_id
	 */
	public function comment_exists( $comment )
	{
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT comment_ID FROM ' . $wpdb->comments . ' WHERE comment_author = %s AND comment_date = %s', $comment->comment_author, $comment->comment_date ) );
	}//end comment_exists

	/**
	 * Convert the post to be easily loggable
	 *
	 * @param $post wp_postobject
	 *
	 * @return $log_data array
	 */
	private function post_log_data( $post )
	{
		// Logging data needs to be truncated so it'll be under Simple DB's limits
		$log_data = array(
			'post_date'     => $post->post->post_date,
			'post_date_gmt' => $post->post->post_date_gmt,
		);

		foreach ( $post->meta as $key => $value )
		{
			if ( strncmp( '_go_channel_time', $key, 16 ) == 0 )
			{
				$log_data[ $key ] = $value;
			}//end if
		}//end foreach

		return $log_data;
	}//end post_log_data

	/**
	 * Ping an endpoint to tell it to get the post
	 *
	 * @param $endpoint string URL that will be requested
	 * @param $post_id int wordpress $post_id to ping
	 */
	public function ping( $endpoint, $post_id, $secret, $filter )
	{
		// check the post_id
		$post_id = $this->sanitize_post_id( $post_id );

		if ( ! $post_id )
		{
			return;
		}//end if

		// don't ping the same endpoint multiple times for the same post for the same change
		if ( isset( $this->pinged[ $endpoint .' '. $post_id ] ))
		{
			return;
		}//end if

		$source = urlencode( admin_url( '/admin-ajax.php', 'http' ) );

		// curl and HTTPS self-signed certificates do not play nice together
		$source = ( defined( 'GO_DEV' ) && GO_DEV ) ? preg_replace( '/^https/', 'http', $source ) : $source;

		// build and sign the request var array
		$query_array = array(
			'action'  => 'go_xpost_ping',
			'source'  => $source,
			'post_id' => $post_id,
			'filter'  => $filter,
		);

		$query_array['signature'] = $this->build_identity_hash( $query_array, $secret );

		if ( 'GET' == go_xpost()->get_request_method() )
		{
			$endpoint_get = $this->build_get_url( $endpoint, $query_array );

			// send the ping
			$return = wp_remote_get( $endpoint_get, array( 'timeout' => 20 ) );
		} // end if
		else
		{
			// send the ping
			$return = wp_remote_request( $endpoint, array( 'timeout' => 20, 'method' => 'POST', 'body' => $query_array ) );
		}// end else

		if ( is_wp_error( $return ) )
		{
			return $this->error( 'go-xpost-ping-error', 'Ping failed', array( 'post_id' => $post_id, 'guid' => get_the_guid( $post_id ), 'source' => $source, 'endpoint' => $endpoint, 'messages' => $return->get_error_messages() ) );
		}// end if

		// save an activity log for this execution instance
		$this->pinged[ $endpoint .' '. $post_id ] = time();

		// log and return success
		apply_filters( 'go_slog', 'go-xpost-send-ping', $endpoint . ' ' . $post_id, array( 'post_id' => $post_id, 'response' => $return['response'] ) );

		return;
	}//end ping

	/**
	 * Receive an incoming request to import a new post
	 */
	public function receive_ping()
	{
		if ( empty( $_REQUEST['source'] ) )
		{
			$this->error_and_die( 'go-xpost-invalid-ping', 'Forbidden or missing parameters', $_REQUEST, 403 );
		}//end if

		// Tell the pinger that we don't need them anymore
		$this->end_http_connection();

		// validate the signature of the sending site
		$ping_array = $_REQUEST;

		// default to a sleep of 3 seconds but allow for an override
		$sleep = isset( $ping_array['sleep'] ) ? absint( $ping_array['sleep'] ) : 3;

		// curl and HTTPS self-signed certificates do not play nice together
		$ping_array['source'] = ( defined( 'GO_DEV' ) && GO_DEV ) ? preg_replace( '/^https/', 'http', $ping_array['source'] ) : $ping_array['source'];

		$signature  = $ping_array['signature'];
		unset( $ping_array['signature'] );

		// die if the signature doesn't match
		if ( ! is_user_logged_in() && $signature != $this->build_identity_hash( $ping_array, go_xpost()->secret ) )
		{
			$this->error_and_die( 'go-xpost-invalid-ping', 'Unauthorized activity', $ping_array, 401 );
		}//end if

		// log this
		apply_filters( 'go_slog', 'go-xpost-received-ping', urldecode( $ping_array['source'] ) . ' ' . $ping_array['post_id'], $ping_array );

		// OK, we're good to go, but let's wait a moment for everything to settle on the other side
		sleep( $sleep );

		// build and sign the request var array
		$query_array = array(
			'action'  => 'go_xpost_pull',
			'post_id' => (int) $_REQUEST['post_id'],
			'filter'  => $_REQUEST['filter'],
		);

		$query_array['signature'] = $this->build_identity_hash( $query_array, go_xpost()->secret );

		if ( 'GET' == go_xpost()->get_request_method() )
		{
			$endpoint_get = $this->build_get_url( urldecode( $ping_array['source'] ), $query_array );

			// fetch and decode the post
			$pull_return = wp_remote_get( $endpoint_get );
		}
		else
		{
			$endpoint_url = urldecode( $ping_array['source'] );

			// fetch and decode the post
			$pull_return = wp_remote_request( $endpoint_url, array( 'method' => 'POST', 'body' => $query_array ) );
		} // end else

		// confirm we got a response
		if ( is_wp_error( $pull_return ) || ! ( $body = wp_remote_retrieve_body( $pull_return ) ) )
		{
			apply_filters( 'go_slog', 'go-xpost-response-error', 'Original post could not be retrieved (source: ' . $_REQUEST['source'] . ')', $query_array );
			die;
		}// end if

		$post = unserialize( $body );

		// confirm we got a good result
		if ( is_wp_error( $post ) || ! isset( $post->post->guid ) )
		{
			$more_data = array( 'query_array' => $query_array );
			if ( is_wp_error( $post ) )
			{
				$more_data['error_messages'] = $post->get_error_messages();
			}// end if

			apply_filters( 'go_slog', 'go-xpost-retrieve-error', 'Original post was not a valid object after unserializing (source: ' . $_REQUEST['source'] . ')', $more_data );
			die;
		}// end if

		// report our success
		apply_filters( 'go_slog', 'go-xpost-retrieved-post', 'Original post as retrieved by get_post (GUID: '. $post->post->guid . ')', $this->post_log_data( $post ) );

		// allow the GO_Xpost class (and others) to do something in response to the ping being received
		do_action( 'go_xpost_receive_ping', $post );

		// save
		$post_id = $this->save_post( $post );
		die;
	}//end receive_ping

	/**
	 * Build a GET URL from an endpoint and query_array
	 *
	 * @param $endpoint string URL that will be requested
	 * @param $query array and get parameters that need to be added to the query string
	 *
	 * @return string URL with query string added
	 */
	private function build_get_url( $url, $query = array() )
	{
		// split out the query string from the url
		$parts = parse_url( $url );

		// split the query string off the base URL
		list( $url ) = explode( '?', $url );

		if ( isset( $parts['query'] ) )
		{
			// turn the query string into an associative array
			parse_str( $parts['query'], $new_query );

			// override any variables in the original url with the variables in the passed in query
			$query = array_merge( $new_query, $query );
		}// end if

		// concatenate!
		$url .= '?' . http_build_query( $query );

		return $url;
	}// end build_get_url


	/**
	 * Clean up the post_id
	 *
	 * @param $post_id int wordpess $post_id to sanitize
	 *
	 * @return sanitized $post_id
	 */
	public function sanitize_post_id( $post_id )
	{
		$post_id = (int) $post_id;
		if ( $the_post = wp_is_post_revision( $post_id ) )
		{
			$post_id = $the_post;
		}//end if

		return $post_id;
	}//end sanitize_post_id

	/**
	 * Save post attachment
	 *
	 * @param $post wp_postobject $post to save attachment
	 *
	 * @return $post_id
	 */
	public function save_attachment( $post )
	{
		// a lot of the code below comes from
		// http://plugins.svn.wordpress.org/bsuite-drop-folder/trunk/bsuite-drop-folder.php
		// and
		// http://core.svn.wordpress.org/tags/2.9.2/wp-admin/import/wordpress.php

		apply_filters( 'go_slog', 'go-xpost-save-attachment', 'Started attachment saving', array( 'post_id' => $post->post->ID, 'guid' => $post->post->guid, 'url' => $post->file->url ) );

		// create a location for this file
		$file = wp_upload_bits( basename( $post->file->url ), null, '', $post->post->post_date );

		// check and enforce limits on file types
		if ( $file['error'] )
		{
			return $this->error(
				'go-xpost-attachment-badfiletype',
				'File upload error for GUID: ' . $post->post->guid . ' (' . $file['error'] . ')',
				array(
					'post_id' => $post->post->ID,
					'url' => $post->file->url, 
					'error' => $file['error']
				)
			);
		}//end if

		$file_path = $file['file'];

		// fetch the remote url and write it to the placeholder file
		$headers = wp_get_http( $post->file->url, $file['file'] );

		//Request failed
		if ( ! $headers )
		{
			@unlink( $file['file'] );
			return $this->error( 'go-xpost-attachment-unreachable', 'Remote server did not respond for ' . $post->file->url, array( 'post_id' => $post->post->ID, 'guid' => $post->post->guid ) );
		}//end if

		// make sure the fetch was successful
		if ( $headers['response'] != '200' )
		{
			@unlink( $file['file'] );
			return $this->error( 'go-xpost-attachment-unreachable', sprintf( 'Remote file returned error response %1$d %2$s for %3$s', $headers['response'], get_status_header_desc( $headers['response'] ), $post->file->url ), array( 'post_id' => $post->post->ID, 'guid' => $post->post->guid ) );
		}//end if
		elseif ( isset($headers['content-length']) && filesize( $file['file'] ) != $headers['content-length'] )
		{
			@unlink( $file['file'] );
			return $this->error( 'go-xpost-attachment-badsize', 'Remote file is incorrect size '. $post->file->url, array( 'post_id' => $post->post->ID, 'guid' => $post->post->guid ) );
		}//end elseif

		// do actions for replication
		$file = apply_filters( 'wp_handle_upload', array(
			'file' => $file['file'],
			'url' => $file['url'],
			'type' => $headers['content-type'],
		), 'go-xpost' );

		do_action( 'wp_create_file_in_uploads', $file['file'] );

		/* @TODO: None of this code does anything remotely useful, but it should, so I'm leaving it for future generations
		// this is messed up, the parent object is *never* added to an attachment
		// look up parent post, fail if it doesn't exist
		if ( isset( $post->parent ) )
		{
			// Correct the parent ID in the post object
			$post->post->post_parent = $this->post_exists( $post->parent );
			if ( ! $post->post->post_parent )
			{
				@unlink( $file );
				return $this->error( 'go-xpost-attachment-noparent', 'Failed to find post parent (GUID: '. $post->parent->guid .') for GUID: '. $post->post->guid, $this->post_log_data( $post ) );
			}//end if
		}// end if
		*/
		// this is also screwy.  The author object is *never* added to an attachment
		if ( isset( $post->author ) )
		{
			$post->post->post_author = $this->get_author( $post->author );
		}

		// check if the post exists
		if ( ! ( $post_id = $this->post_exists( $post->post ) ) )
		{
			$post_id = wp_insert_attachment( (array) $post->post, $file['file'] );
			$action  = 'Inserted';
		}//end if
		else
		{
			$post->post->ID = $post_id;
			$post_id = wp_insert_attachment( (array) $post->post, $file['file'] );
			$action = 'Updated';
		}//end else

		if ( is_wp_error( $post_id ) )
		{
			@unlink( $file['file'] );
			return $this->error( 'go-xpost-failed-save', 'Failed to save attachment (GUID: ' . $post->post->guid . ')', $post_id );
		}//end if

		// set the post meta as received for the post
		foreach ( (array) $post->meta as $meta_key => $meta_values )
		{
			switch ( $meta_key )
			{
				case '_wp_attachment_metadata':
				case '_wp_attached_file': // don't overwrite the local attachment meta
					break;
				case '_edit_lock':
				case '_edit_last': // edit last and lock are unimportant in the destination
					break;
				default:
					if ( ! empty( $meta_values ) )
					{
						delete_post_meta( $post_id, $meta_key );
						add_post_meta( $post_id, $meta_key, $meta_values );
					}//end if
			}//end switch
		}//end foreach

		// not entirely sure if I should delete postmeta
		delete_post_meta( $post_id, '_wp_attachment_metadata' );

		// generate and insert new postmeta for attachment
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $file_path ) );

		// set any terms on the attachment
		if ( isset( $post->terms ) )
		{
				foreach ( (array) $post->terms as $tax => $terms )
				{
					wp_set_object_terms( $post_id, $terms, $tax, FALSE );
				}//end foreach
		}//END if

		// success log
		apply_filters( 'go_slog', 'go-xpost-save-attachment', 'Success! '. $action .' (ID: '. $post_id .', GUID: '. $post->post->guid . ')', $this->post_log_data( $post ) );

		return $post_id;
	}//end save_attachment

	/**
	 * Save post
	 *
	 * @param $post wp_postobject
	 *
	 * @return $post_id
	 */
	public function save_post( $post )
	{
		// allow other plugins to modify the post
		$post = apply_filters( 'go_xpost_pre_save_post', $post );

		// look up parent post, fail if it doesn't exist
		if ( isset( $post->parent ) && ( ! $parent_id = $this->post_exists( $post->parent ) ) )
		{
			return $this->error( 'go-xpost-failed-parent', 'Failed to find post parent (GUID: '. $post->parent->guid .') for GUID: '. $post->post->guid, $this->post_log_data($post) );
		}//end if

		$post->post->post_author = $this->get_author( $post->author );

		// update the post dates based on the local gmt offset, where possible
		if ( '0000-00-00 00:00:00' != $post->post->post_date_gmt )
		{
			$post->post->post_date = $this->utc_to_local( $post->post->post_date_gmt );
		}// end if

		if ( '0000-00-00 00:00:00' != $post->post->modified_date_gmt )
		{
			$post->post->modified_date = $this->utc_to_local( $post->post->modified_date_gmt );
		}// end if

		// if the post_date is old, we do not want to do subscriber notifications when receiving the xpost
		if ( strtotime( 'today - 8 hours' ) > strtotime( $post->post->post_date ) )
		{
			define( 'WP_IMPORTING', TRUE );
		}// end if

		// check if the post exists
		// insert or update as appropriate
		if ( ! ( $post_id = $this->post_exists( $post->post ) ) )
		{
			// don't insert draft posts that are new
			if ( 'draft' == $post->post->post_status )
			{
				return '';
			}// end if

			$post_id = wp_insert_post( (array) $post->post, true );
			$action  = 'Inserted';
		}//end if
		else // the post exists, so update it
		{
			// don't track revisions for this update
			remove_post_type_support( $post->post->post_type, 'revisions' );
			$post->post->ID = $post_id;
			$post_id = wp_insert_post( (array) $post->post, true );
			$action = 'Updated';
		}//end else

		// go home crying if we encounter an error inserting or updating the post
		if ( is_wp_error( $post_id ) )
		{
			return $this->error( 'go-xpost-failed-save', 'Failed to save post (GUID: ' . $post->post->guid . ')', $this->post_log_data( $post ) );
		}//end if

		apply_filters( 'go_slog', 'go-xpost-meta', 'Xpost meta fields', array_keys( $post->meta ) );

		// set the post meta as received for the post
		foreach ( (array) $post->meta as $meta_key => $meta_values )
		{
			delete_post_meta( $post_id, $meta_key );

			switch ( $meta_key )
			{
				case '_edit_lock':
				case '_edit_last': // edit last and lock are unimportant in the destination
					continue;
				case strpos( $meta_key, '_thumbnail_id' ) !== FALSE: // Thumbnail Image
					if ( $post->$meta_key && $this->post_exists( $post->$meta_key->post ) )
					{
						$new_img_id = $this->post_exists( $post->$meta_key->post );
					}//end if
					else
					{
						$new_img_id = $this->save_attachment( $post->$meta_key );
					}//end else

					if ( isset( $new_img_id ) )
					{
						add_post_meta( $post_id, $meta_key, $new_img_id );
					}//end if
					break;
				default:
					if ( ! empty( $meta_values ) )
					{
						add_post_meta( $post_id, $meta_key, $meta_values );
					}//end if
			}//end switch
		}//end foreach

		// update the comment_count from the post meta because
		// wp_insert_post() does not update that field
		$this->update_comment_count( $post_id, 0, 0 );

		// set the taxonomy terms as received for the post
		if ( isset( $post->terms ) )
		{
			foreach ( (array) $post->terms as $tax => $terms )
			{
				wp_set_object_terms( $post_id, $terms, $tax, FALSE );
			}//end foreach
		}

		do_action( 'go_xpost_save_post', $post_id, $post );

		// success log
		apply_filters( 'go_slog', 'go-xpost-save-post', 'Success! ' . $action . ' (ID: '. $post_id . ', GUID: ' . $post->post->guid . ')', $this->post_log_data( $post ) );

		return $post_id;
	}//end save_post

	/**
	 * update the post comment_count based on its go_xpost_comment_count
	 * post meta value
	 */
	public function update_comment_count( $post_id, $new, $old )
	{
		if ( $xpost_comment_count = get_post_meta( $post_id, 'go_xpost_comment_count', TRUE ) )
	 	{
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'comment_count' => $xpost_comment_count), array( 'ID' => $post_id ) );
			clean_post_cache( $post_id );
	 	}
	}//END update_comment_count

	/**
	 * Send a post for xposting
	 */
	public function send_post()
	{
		// enforce the signed request for users who are not logged in
		if ( ! is_user_logged_in() )
		{
			// validate the signature of the sending site
			$ping_array = $_REQUEST;
			$signature  = $ping_array['signature'];
			unset( $ping_array['signature'] );

			// die if the signature doesn't match
			if ( $signature != $this->build_identity_hash( $ping_array, go_xpost()->secret ) )
			{
				$this->error_and_die( 'go-xpost-invalid-pull', 'Unauthorized activity', $_REQUEST, 401 );
			}//end if
		}//end if
		else // allow logged in users to make unsigned requests for easier debugging
		{
			$ping_array = $_REQUEST;
		}//end else

		// if we don't have a post ID, don't continue
		if ( ! isset( $ping_array['post_id'] ) || ! is_numeric( $ping_array['post_id'] ) )
		{
			$this->error_and_die( 'go-xpost-invalid-pull', 'Forbidden or missing parameters', $ping_array, 403 );
		}//end if

		// Load the filter we got passed
		$filter = go_xpost()->filters[ $_REQUEST['filter'] ];

		// we're good, get the post, filter it, and then echo it out
		$post = $filter->post_filter( $this->get_post( $ping_array['post_id'] ), $ping_array['post_id'] );

		$post = apply_filters( 'go_xpost_pre_send_post', $post );

		if ( isset( $ping_array['output'] ) && 'prettyprint' == $ping_array['output'] )
		{
			$post = '<pre>' . print_r( $post, TRUE ) . '</pre>';
		}//end if
		else
		{
			$post = serialize( $post );
		}//end else

		// Ending the connection while passing just the post content to prevent go_slog filter from causing issues
		$this->end_http_connection( $post );

		apply_filters( 'go_slog', 'go-xpost-send-post', $_SERVER['REMOTE_ADDR'] . ' ' . $ping_array['post_id'], $ping_array );

		// all done, bye bye
		die;
	}//end send_post

	/**
	 * Get the author of the post
	 */
	public function get_author( $author )
	{
		if ( ! is_object( $author ) )
		{
			// default to admin when the data is screwy
			return 1;
		}// end if

		// Check if author exists, allow it to be hooked if not
		if ( ! isset( $author->data ) || ! is_object( $author->data ) || ! $post_author = get_user_by( 'email', $author->data->user_email ) )
		{
			// @TODO: this needs to be fixed: in the case of this not being hooked, it will be $author->ID, however, false, 0, or -1 might be more accurate?
			return apply_filters( 'go_xpost_unknown_author', 0, $author );
		}//end if

		// ID could be different so lets replace it with the local one
		// @TODO: Pro currently has a lot of email address duplication in user accounts.  This may cause surprising effects here. (see Om and Ingram)
		return $post_author->ID;
	}// end get_author

	/**
	 * function build_identity_hash
	 * @param mixed $params Array or String (of query vars)
	 * @return string $signature
	 * @author Vasken Hauri
	 */
	public function build_identity_hash( $params, $secret )
	{
		if ( ! is_array( $params ) )
		{
			parse_str( $params, $param_arr );
			$params = $param_arr;
		}// end if

		//sort the params
		ksort( $params );

		//now create the string to sign
		$string_to_sign = implode( '&', $params );

		//calculate an HMAC with SHA256 and base64-encoding a la Amazon
		//http://mierendo.com/software/aws_signed_query/
		$signature = base64_encode( hash_hmac( 'sha256', $string_to_sign, $secret ) );

		//make sure the signature is url_encoded properly
		$signature = str_replace( '%7E', '~', rawurlencode( $signature ) );

		return $signature;
	}// end build_identity_hash

	/**
	 * get a comment object and associated data: post, comment meta and
	 * comment parent.
	 *
	 * @param $comment_id int/string id of the comment to retrieve
	 * @return an object with error, comment, post, meta and comment_parent
	 *  members.
	 */
	public function get_comment( $comment_id )
	{
		$result = new StdClass;
		$result->error = NULL;

		$comment = get_comment( $comment_id );

		if ( empty( $comment ) )
		{
			$result->error = 'comment ' . $comment_id . ' not found';
			return $result;
		}//END if

		// check if we already have the post object related to this comment
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
			$result->error = 'Could not get the post associated with the comment (POST ID: ' . $comment->comment_post_ID . ' Comment ID: ' . $comment->comment_ID . ')';
			return $result;
		} // END if

		$result->comment = $comment;
		$result->post    = $post;

		// Get comment meta
		$comment_meta = get_comment_meta( $comment_id );

		if ( ! empty( $comment_meta ) )
		{
			$result->meta = array();

			foreach ( $comment_meta as $mkey => $mval )
			{
				$result->meta[ $mkey ] = maybe_unserialize( $mval[0] );
			} // END foreach
		} // END if

		// Get parent
		if( $comment->comment_parent )
		{
			if ( $parent = get_comment( $comment->comment_parent ) )
			{
				$result->parent = $parent;
			} // END if
			else
			{
				$result->error = 'Could not get the parent associated with the comment (PARENT ID: ' . $comment->comment_parent . ' Comment ID: ' . $comment->comment_ID . ')';
			} // END else
		}

		return $result;
	}//END get_comment

	/**
	 * save a comment
	 *
	 * @param $comment a comment object as returned from get_comment() in
	 *  this class.
	 * @retval objecgt the comment object updated or inserted
	 */
	public function save_comment( $comment )
	{
		// Keep this for output purposes
		$old_comment_id = $comment->comment->comment_ID;
		unset( $comment->comment->comment_ID );

		// Does the comment's post exist?
		if ( ! $post_id = $this->post_exists( $comment->post ) )
		{
			return $this->error( 'go-xpost-failed-save-comment', 'Comment post not found on destination blog',  $comment );
		} // END if

		$comment->comment->comment_post_ID = $post_id;

		// Does the comment's parent exist?
		if ( isset( $comment->parent ) )
		{
			$parent_id = $this->comment_exists( $comment->parent );
			if ( ! $parent_id )
			{
				return $this->error( 'go-xpost-invalid-comment-parent', 'Comment parent ' . $comment->parent->comment_ID . ' not found on destination blog' );
			}

			$comment->comment->comment_parent = $parent_id;
		} // END if

		// Check if comment already exists
		if ( $comment_id = $this->comment_exists( $comment->comment ) )
		{
			$comment->comment->comment_ID = $comment_id;
			wp_update_comment( (array) $comment->comment );
		} // END if
		else
		{
			$comment_id = wp_insert_comment( (array) $comment->comment );
		} // END else
		$comment->comment->comment_ID = $comment_id;

		// Is there comment meta?
		if ( isset( $comment->meta ) && is_array( $comment->meta ) )
		{
			foreach ( $comment->meta as $meta_key => $meta_value )
			{
				delete_comment_meta( $comment_id, $meta_key );
				add_comment_meta( $comment_id, $meta_key, $meta_value );
			} // END foreach
		} // END if

		return $comment;
	}//END save_comment

	/**
	 * Convert UTC time to the blog configured timezone time
	 */
	public function utc_to_local( $datetime_string, $offset = FALSE )
	{
		if ( ! $offset )
		{
			$offset = get_option( 'gmt_offset' );
		}//end if

		$tz   = new DateTimeZone( 'UTC' );
		$date = new DateTime( $datetime_string, $tz );

		$date->modify( $offset .' hours' );

		return $date->format('Y-m-d H:i:s');
	}//end utc_to_local

	/**
	 * Log an error and get a WP_Error object
	 */
	public function error( $code, $message, $data )
	{
		apply_filters( 'go_slog', $code, $message, $data );
		return new WP_Error( $code, $message, $data );
	}//end error

	/**
	 * Output the error and stop execution
	 */
	public function error_and_die( $code, $message, $data, $http_code )
	{
		$this->error( $code, $message, $data );
		header( $_SERVER[ 'SERVER_PROTOCOL' ] . ' ' . $http_code . ' ' . $message, TRUE, $http_code );
		die;
	}//end error_and_die
}//end class

function go_xpost_util()
{
	global $go_xpost_util;

	if ( ! isset( $go_xpost_util ) )
	{
		$go_xpost_util = new GO_XPost_Utilities();
	}// end if

	return $go_xpost_util;
}// end go_xpost_util
