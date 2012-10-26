<?php
/*
Plugin Name: GO Crosspost
Plugin URI: http://gigaom.com
Description: Pings another domain to let it know a new relevant post has been added which triggers that domain to then pull the data it needs to replicate the post in WP.
Author: GigaOM
Version: 1.0
Author URI: http://gigaom.com/
*/

require_once( __DIR__ .'/plugin/migrator.php' ); 

global $goxpost;

// Include the relevant file for this site
$host = parse_url( site_url() , PHP_URL_HOST );
$host = explode( '.' , $host );
switch( $host[0] )
{
	// Staging sites
	case 'gigaompaidcontentstaging': // pC staging on WordPress.com
		require_once( __DIR__ .'/conf/paidcontent.php' );
		$goxpost = new Go_Xpost( 'http://gigaomstaging.wordpress.com/wp-admin/admin-ajax.php' ); // Pushes to GO staging
		break;
	case 'pc': // Casey's pC staging
		require_once( __DIR__ .'/conf/paidcontent.php' );
		$goxpost = new Go_Xpost( 'core.gomcom.arcgate.gostage.it/wp-admin/admin-ajax.php' ); // Pushes to GO staging
		break;
	case 'paidcontent': // pC staging
	case 'gostage': // pC staging
		require_once( __DIR__ .'/conf/paidcontent.php' );
		$goxpost = new Go_Xpost( 'http://gigaom.wp.gostage.it/wp-admin/admin-ajax.php' ); // Pushes to GO staging
		break;
	case 'gigaomstaging': // GO staging on WordPress.com
		require_once( __DIR__ .'/conf/paidcontent.php' );
		$goxpost = new Go_Xpost( 'http://gigaompaidcontentstaging.wordpress.com/wp-admin/admin-ajax.php' ); // Pushes to GO staging
		break;
	case 'core': // Casey's GO staging
		require_once( __DIR__ .'/conf/gigaom.php' );
		$goxpost = new Go_Xpost( 'http://pc.gomcom.arcgate.gostage.it/wp-admin/admin-ajax.php' ); // Pushes to pC staging
		break;	
	case 'gigaom': // GO staging
		require_once( __DIR__ .'/conf/gigaom.php' );
		$goxpost = new Go_Xpost( 'http://gostage.paidcontent.org/wp-admin/admin-ajax.php' ); // Pushes to pC staging
		break;	

	// Live sites
	case 'gigaom2': // GigaOM.com
		require_once( __DIR__ .'/conf/gigaom.php' );
		$goxpost = new Go_Xpost( 'http://gigaompaidcontent.wordpress.com/wp-admin/admin-ajax.php' ); // Pushes to paidContent.org
		break;
	case 'gigaompaidcontent': // paidContent.org
		require_once( __DIR__ .'/conf/paidcontent.php' );
		$goxpost = new Go_Xpost( 'http://gigaom2.wordpress.com/wp-admin/admin-ajax.php' ); // Pushes to GigaOM.com
		break;
}

function go_xpost_pre_save_post( $post )
{
	global $goxpost;

	foreach( (array) $post->meta as $mkey => $mval )
	{
		if( preg_match( '/^_go_channel_time-/' , $mkey ))
		{
			$source_offset = $goxpost->utc_offset_from_dates($post->post->post_date_gmt, $post->post->post_date);
									
			$switched = (0 - $source_offset); // Flip the offset around so we can use it to get to UTC instead of Local
						
			$gmt_channel_time = $goxpost->utc_to_local($mval, $switched);
						
			$post->meta[ $mkey ] = $goxpost->utc_to_local( $gmt_channel_time );
						
			go_slog( 'go-xpost-debug' , 'Adjusting channel time on '. $post_id .' '. $mval .' -> '. $post->meta[ $mkey ] , $post );
		}
	}

	return $post;
}