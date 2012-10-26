<?php

class Go_Migrator
{
	public $log;
	public $source_domain = '';
	public $guest_author_id = 16281271;

	public function sanitize_post_id( $post_id )
	{
		$post_id = (int) $post_id;
		if( $the_post = wp_is_post_revision( $post_id ))
			$post_id = $the_post;

		return $post_id;
	}

	public function post_exists( $post )
	{
	    global $wpdb;
		
	    $post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM '. $wpdb->posts .' WHERE guid = %s', trim( $post->guid )));

	    return $post_id;
	}

	public function utc_offset_from_dates( $utc_string , $local_string )
	{

		$tz = new DateTimeZone( 'UTC' );
		$utc = new DateTime( $utc_string , $tz );
		$local = new DateTime( $local_string , $tz );
		$offset = $utc->diff( $local );

		return $offset->format('%R%h');
	}

	public function utc_to_local( $datetime_string , $offset = FALSE )
	{
		if( ! $offset )
			$offset = get_option( 'gmt_offset' );

		$tz = new DateTimeZone( 'UTC' );
		$date = new DateTime( $datetime_string , $tz );

		$date->modify( $offset .' hours' );

		return $date->format('Y-m-d H:i:s');;
	}

	public function get_post( $post_id )
	{
		// check the post_id
		$post_id = $this->sanitize_post_id( $post_id );

		// confirm that the requested post exists
		if( ! get_post( $post_id ))
			return go_slog_and_error( 'go-xpost-failed-to-get-post' , 'Failed to get the requested post (ID: '. $post_id .')', $r->post );

		// get the post
		$r->post = clone get_post( $post_id );
		if( is_wp_error( $r->post ) )
			return go_slog_and_error( 'go-xpost-failed-to-get-post' , 'Failed to get the requested post (ID: '. $post_id .')', $r->post );

	 	// unset the post ID in the post object now to prevent risk of overwriting a post in another blog
		unset( $r->post->ID );

		// get the postmeta
		foreach( (array) get_metadata( 'post' , $post_id ) as $mkey => $mval )
			$r->meta[ $mkey ] = maybe_unserialize( $mval[0] );

		// get the terms
		foreach( (array) wp_get_object_terms( $post_id, get_object_taxonomies( $r->post->post_type )) as $term )
			$r->terms[ $term->taxonomy ][] = $term->name;

		if( $r->post->post_parent )
			$r->parent = get_post( $r->post->post_parent );

		// map the thumbnail post ID to post/guid
		// this is compatible with the http://wordpress.org/extend/plugins/multiple-post-thumbnails/ plugin available on VIP
		foreach( (array) $r->meta as $mkey => $mval )
		{
			if(( strpos( $mkey , '_thumbnail_id' ) !== FALSE ) && ( $attachment = $this->get_attachment( $mval )))
			{
				$r->$mkey = $attachment;
			}
		}

		// Get author data
		$r->author = get_userdata( $r->post->post_author );
	
		$r->origin->ID = $post_id;		
		$r->origin->permalink = get_permalink( $post_id );

		// add meta to identify this as a crosspost and link to the original
		$r->meta['go_mancross_redirect'] = $r->origin->permalink;

	 	// unset the meta that we don't want to attempt to copy
		unset( $r->meta['_edit_lock'] );
		unset( $r->meta['_edit_last'] );
		unset( $r->meta['_go_comment_cache'] );
		unset( $r->meta['_go_comment_cache_full'] );
		unset( $r->meta['go_oc_settings'] );
		unset( $r->meta['oc_commit_id'] );
		unset( $r->meta['oc_metadata'] );
		unset( $r->meta['_go_log'] );
		unset( $r->meta['_go_comment_cache'] );
		
		return $r;
	}

	public function save_post( $post )
	{

		// allow other plugins to modify the post
		$post = apply_filters( 'go_xpost_pre_save_post' , $post );

		// look up parent post, fail if it doesn't exist
		if( isset( $post->parent ) && ( ! $parent_id = $this->post_exists( $post->parent )))
			return go_slog_and_error( 'go-xpost-failed-parent' , 'Failed to find post parent (GUID: '. $post->parent->guid .') for GUID: '. $post->post->guid , $this->post_log_data($post) );

		// Check if author exists, make it a guest author if not
		$guest_author = FALSE;
		if( ! $post_author = get_user_by( 'email' , $post->author->data->user_email ))
		{
			$post->post->post_author = $this->guest_author_id;
			$guest_author = TRUE;
		}
		else 
		{
			// ID could be different so lets replace it with the local one
			$post->post->post_author = $post_author->ID;
		}

		// update the post dates based on the local gmt offset
		$post->post->post_date = $this->utc_to_local( $post->post->post_date_gmt );
		$post->post->modified_date = $this->utc_to_local( $post->post->modified_date_gmt );

		// check if the post exists
		// insert or update as appropriate
		if( ! ( $post_id = $this->post_exists( $post->post )))
		{
			$post_id = wp_insert_post( (array) $post->post );
			$action = 'Inserted';
		}
		else // the post exists, so update it
		{
			// don't track revisions for this update
			remove_post_type_support( $post->post->post_type , 'revisions' );
			$post->post->ID = $post_id;
			$post_id = wp_insert_post( (array) $post->post );
			$action = 'Updated';
		}

		// go home crying if we encounter an error inserting or updating the post
		if( is_wp_error( $post_id ) )
			return go_slog_and_error( 'go-xpost-failed-save' , 'Failed to save post (GUID: '. $post->post->guid .')' , $this->post_log_data($post) );

		// set the post meta as received for the post
		foreach( (array) $post->meta as $meta_key => $meta_values )
		{
			delete_post_meta( $post_id , $meta_key );
			switch( $meta_key )
			{
				case '_edit_lock':
				case '_edit_last': // edit last and lock are unimportant in the destination
					continue;
				case strpos( $meta_key, '_thumbnail_id' ) !== FALSE: // Thumbnail Image												
					if( $post->$meta_key && $this->post_exists( $post->$meta_key->post ))
						$new_img_id = $this->post_exists( $post->$meta_key->post );
					else 
						$new_img_id = $this->save_attachment( $post->$meta_key );
					
					if (isset($new_img_id))
						add_post_meta( $post_id , $meta_key , $new_img_id );
					break;
				default:				
					if( ! empty( $meta_values ))
						add_post_meta( $post_id , $meta_key , $meta_values );
			}
		}

		// set the taxonomy terms as received for the post
		foreach( (array) $post->terms as $tax => $terms )
			wp_set_object_terms( $post_id , $terms , $tax , FALSE );

		// Set guest author data if necessary from the check above
		if( $guest_author == TRUE && method_exists( 'Go_GuestPost_Plugin' , 'save_guest_post_data' ))
		{
			// Do we want to set Publication/Source data as well? Maybe using variables inside of the config classes for each domain that we pass on?
			$guest_author_data = array(
				'post_id' => $post_id,
				'author_override' => TRUE,
			 	'source_override' => FALSE,
				'author_name' => $post->author->data->display_name, 
				'author_url' => $post->author->data->user_url, 
			);

			Go_GuestPost_Plugin::save_guest_post_data( $guest_author_data );
		}

		// success log
		go_slog( 'go-xpost-save-post' , 'Success! '. $action .' (ID: '. $post_id .', GUID: '. $post->post->guid .')' , $this->post_log_data($post) );

		return $post_id;
	}

	public function get_attachment( $post_id )
	{
		// get the attachment path and URL from the source blog
		$this->path_base = wp_upload_dir();

		$post_id = (int) $post_id;

		// confirm that the requested post exists
		if( ! get_post( $post_id ))
			return go_slog_and_error( 'go-xpost-failed-to-get-attachment' , 'Failed to get the requested attachment (ID: '. $post_id .')', $this->post_log_data($r->post) );


		// get the post
		$r->post = clone get_post( $post_id );
		if( is_wp_error( $r->post ) )
			return go_slog_and_error( 'go-xpost-failed-to-get-attachment' , 'Failed to get the requested attachment (ID: '. $post_id .')', $this->post_log_data($r->post) );

	 	// unset the post ID in the post object now to prevent risk of overwriting a post in another blog
		unset( $r->post->ID );

		// get the postmeta
		foreach( (array) get_metadata( 'post' , $post_id ) as $mkey => $mval )
			$r->meta[ $mkey ] = maybe_unserialize( $mval[0] );

		// get file paths and URLs to the attached file				
		$r->file->url = wp_get_attachment_url( $post_id );

		// get the terms
		foreach( (array) wp_get_object_terms( $post_id, get_object_taxonomies( $r->post->post_type )) as $term )
			$r->terms[ $term->taxonomy ][] = $term->name;

		$r->origin->ID = $post_id;
		$r->origin->permalink = $r->file->url;

	 	// unset the attachment meta that needs to be regenerated on the remote site
		unset( $r->meta['_wp_attachment_metadata'] );
		unset( $r->meta['_wp_attached_file'] );

		return $r;
	}

	public function save_attachment( $post )
	{
		// a lot of the code below comes from
		// http://plugins.svn.wordpress.org/bsuite-drop-folder/trunk/bsuite-drop-folder.php
		// and
		// http://core.svn.wordpress.org/tags/2.9.2/wp-admin/import/wordpress.php

		// create a location for this file
		$file = wp_upload_bits( basename( $post->file->url ) , 0 , '' , $post->post->post_date );

		// check and enforce limits on file types
		if( $file['error'] )
			return go_slog_and_error( 'go-xpost-attachment-badfiletype' , 'Bad file for GUID: '. $post->post->guid , $file );

		$file_path = $file['file'];

		// fetch the remote url and write it to the placeholder file		
		$headers = wp_get_http( $post->file->url, $file['file'] );

		//Request failed
		if( ! $headers )
		{
			@unlink( $file['file'] );
			return go_slog_and_error( 'go-xpost-attachment-unreachable' , 'Remote server did not respond for '. $post->file->url , $this->post_log_data($post) );
		}

		// make sure the fetch was successful
		if ( $headers['response'] != '200' )
		{
			@unlink( $file['file'] );
			return go_slog_and_error( 'go-xpost-attachment-unreachable' , sprintf( 'Remote file returned error response %1$d %2$s for %3s' , $headers['response'] , get_status_header_desc( $headers['response'] ) , $post->file->url ) , $this->post_log_data($post) );
		}
		elseif ( isset($headers['content-length']) && filesize( $file['file'] ) != $headers['content-length'] )
		{
			@unlink( $file['file'] );
			return go_slog_and_error( 'go-xpost-attachment-badsize' , 'Remote file is incorrect size '. $post->file->url , $this->post_log_data($post) );
		}

		$url = $file['url'];
		$file = $file['file'];

		// do actions for replication
		$file = apply_filters( 'wp_handle_upload', array( 
			'file' => $file['file'], 
			'url' => $file['url'], 
			'type' => $headers['content-type'] 
		), 'go-xpost' );
		do_action( 'wp_create_file_in_uploads', $file['file'] );

		// look up parent post, fail if it doesn't exist
		if( $post->parent && ( ! $parent_id = $this->post_exists( $post->parent )))
		{
			@unlink( $file );
			return go_slog_and_error( 'go-xpost-attachment-noparent' , 'Failed to find post parent (GUID: '. $post->parent->guid .') for GUID: '. $post->post->guid , $this->post_log_data($post) );
		}

		// Correct the parent ID in the post object, based on the above lookup
		$post->post->post_parent = $parent_id;

		// Check if author exists, make it a guest author if not
		if( ! $post_author = get_user_by( 'email' , $post->author->data->user_email ))
		{
			$post->post->post_author = $this->guest_author_id;
		}
		else 
		{
			// ID could be different so lets replace it with the local one
			$post->post->post_author = $post_author->ID;
		}


		// check if the post exists
		if( ! ( $post_id = $this->post_exists( $post->post )))
		{
			$post_id = wp_insert_attachment( (array) $post->post, $file );
			$action = 'Inserted';
		}
		else
		{
			$post->post->ID = $post_id;
			$post_id = wp_insert_attachment( (array) $post->post, $file );
			$action = 'Updated';
		}

		if( is_wp_error( $post_id ) )
		{
			@unlink( $file );
			return go_slog_and_error( 'go-xpost-failed-save' , 'Failed to save attachment (GUID: '. $post->post->guid .')' , $post_id );
		}

		// set the post meta as received for the post
		foreach( (array) $post->meta as $meta_key => $meta_values )
		{
			switch( $meta_key )
			{
				case '_wp_attachment_metadata':
				case '_wp_attached_file': // don't overwrite the local attachment meta
					break;
				case '_edit_lock':
				case '_edit_last': // edit last and lock are unimportant in the destination
					break;
				default:
					if( ! empty( $meta_values ))
					{
						delete_post_meta( $post_id , $meta_key );
						add_post_meta( $post_id , $meta_key , $meta_values );
					}
			}
		}

		// not entirely sure if I should delete postmeta
		delete_post_meta( $post_id , '_wp_attachment_metadata' );

		// generate and insert new postmeta for attachment
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $file_path ));

		// set any terms on the attachment
		foreach( (array) $post->terms as $tax => $terms )
		{
			wp_set_object_terms( $post_id , $terms , $tax , FALSE );
		}

		// success log
		go_slog( 'go-xpost-save-attachment' , 'Success! '. $action .' (ID: '. $post_id .', GUID: '. $post->post->guid .')' , $this->post_log_data($post) );

		return $post_id;
	}

	public function push( $endpoint , $post_id )
	{
		// check the post_id
		$post_id = $this->sanitize_post_id( $post_id );
		if( ! $post_id )
			return;

		// don't ping the same endpoint multiple times for the same post for the same change
		if( isset( $this->pinged[ $endpoint .' '. $post_id ] ))
			return;

		// build and sign the request var array
		$query_array = array(
			'action'  => 'go_xpost_push',
			'source'  => urlencode( admin_url( '/admin-ajax.php' )),
			'post_id' => $post_id
		);
		$query_array['signature'] = Go_Auth_Helpers::build_identity_hash( $query_array );

		// send the ping
		$return = wp_remote_post( $endpoint , array( 'body' => $query_array , 'timeout' => 20 ));

		// save an activity log for this execution instance
		$this->pinged[ $endpoint .' '. $post_id ] = time();

		// log and return success
		go_slog( 'go-xpost-send-push' , $endpoint .' '. $post_id , $post_id );

		return;
	}
	// END push

	public function receive_push()
	{					
		if( empty( $_POST['source'] ))
			go_slog_and_die( 'go-xpost-invalid-push' , 'Forbidden or missing parameters' , $_POST , 403 );

		// Tell the pinger that we don't need them anymore
		$this->end_http_connection();

		// Remove edit_post action so we don't trigger an accidental crosspost
		remove_action( 'edit_post' , array( $this , 'edit_post' ));

		// validate the signature of the sending site
		$ping_array = $_POST;			
		$signature = $ping_array['signature'];
		unset( $ping_array['signature'] );

		// die if the signature doesn't match
		if( ! is_user_logged_in() && $signature != Go_Auth_Helpers::build_identity_hash( $ping_array ))
			go_slog_and_die( 'go-xpost-invalid-push' , 'Unauthorized activity' , $_POST , 401 );

		// log this
		go_slog( 'go-xpost-received-push' , urldecode( $_POST['source'] ) .' '. $_POST['post_id'] , $_POST );

		// OK, we're good to go, but let's wait a moment for everything to settle on the other side
		sleep( 3 );

		// build and sign the request var array
		$query_array = array(
			'action'  => 'go_xpost_pull',
			'post_id' => (int) $_POST['post_id']
		);
		$query_array['signature'] = Go_Auth_Helpers::build_identity_hash( $query_array );
		
		// fetch and decode the post
		$pull_return = wp_remote_post( urldecode( $_POST['source'] ) , array( 'body' => $query_array ));
		$post = unserialize( $pull_return['body'] );
				
		go_slog( 'go-xpost-retrieved-post' , 'Original post as retreived by get_post (GUID: '. $post->post->guid . ')' , $this->post_log_data($post) );
		
		// check for errors and save
		if( ! is_wp_error( $post ))
			$post = $this->save_post( $post );

		die;
	}
	// END receive_push

	public function send_post()
	{
		// enforce the signed request for users who are not logged in
		if( ! is_user_logged_in() )
		{
			// validate the signature of the sending site
			$ping_array = $_POST;			
			$signature = $ping_array['signature'];
			unset( $ping_array['signature'] );
	
			// die if the signature doesn't match
			if( $signature != Go_Auth_Helpers::build_identity_hash( $ping_array ))
				go_slog_and_die( 'go-xpost-invalid-pull' , 'Unauthorized activity' , $_POST , 401 );
		}
		else // allow logged in users to make unsigned requests for easier debugging
		{
			$ping_array = $_REQUEST;
		}

		// if we don't have a post ID, don't continue
		if( ! isset( $ping_array['post_id'] ) || ! is_numeric( $ping_array['post_id'] ))
			go_slog_and_die( 'go-xpost-invalid-pull' , 'Forbidden or missing parameters' , $ping_array , 403 );

		// we're good, get and send the post
		if( 'prettyprint' == $ping_array['output'] )
			echo '<pre>'. print_r( $this->get_post( $ping_array['post_id'] ) , TRUE ) .'</pre>';
		else
			echo serialize( $this->get_post( $ping_array['post_id'] ));

		go_slog( 'go-xpost-send-post' , $_SERVER['REMOTE_ADDR'] .' '. $ping_array['post_id'] , $ping_array );

		// all done, bye bye
		die;
	}
	// END send_post

	public function post_log_data($post)
	{
		// Logging data needs to be truncated so it'll be under Simple DB's limits
		$log_data = array(
			'post_date' => $post->post->post_date,
			'post_date_gmt' => $post->post->post_date_gmt,
			'go_mancross_redirect' => $post->meta['go_mancross_redirect'],
		);
		
		foreach ($post->meta as $key => $value)
		{
			if (strncmp('_go_channel_time', $key, 16) == 0)
			{
				$log_data[$key] = $value;
			}
		}
		
		return $log_data;
	}
	// END post_log_data

	public function end_http_connection()
	{
		ob_end_clean();
		header('Connection: close');
		header("Content-Encoding: none\r\n");
		
		// buffer all upcoming output
		ob_start();

		print TRUE;
		// get the size of the output
		$size = ob_get_length();
 
		// send headers to tell the browser to close the connection
		header('Content-Length: '.$size);
 
		// flush all output
		ob_end_flush();
		ob_flush();
		flush();
 
		// close current session
		if (session_id()) session_write_close();	
	}
	// END end_connection
}
// END Go_Migrator