<?php
/**
 * Plugin Name: GO Crosspost
 * Plugin URI: http://gigaom.com
 * Description: Pings another domain to let it know a new relevant post has been added which
 *              triggers that domain to then pull the data it needs to replicate the post in WP.
 * Author: GigaOM
 * Version: 1.0
 * Author URI: http://gigaom.com/
 */

require_once __DIR__ . '/components/class-go-xpost-migrator.php';
require_once __DIR__ . '/components/class-go-xpost.php';
require_once __DIR__ . '/components/functions.php';

global $goxpost;

// Include the relevant file for this site
$host = parse_url( site_url(), PHP_URL_HOST );
$host = explode( '.', $host );
switch( $host[0] )
{
	// Staging sites
	case 'gigaompaidcontentstaging': // pC staging on WordPress.com
		include_once __DIR__ .'/components/class-go-xpost-paidcontent.php';
		$goxpost = new GO_XPost_PaidContent( 'http://gigaomstaging.wordpress.com/wp-admin/admin-ajax.php' ); // Pushes to GO staging
		break;
	case 'pc': // Casey's pC staging
		include_once __DIR__ .'/components/class-go-xpost-paidcontent.php';
		$goxpost = new GO_XPost_PaidContent( 'core.gomcom.arcgate.gostage.it/wp-admin/admin-ajax.php' ); // Pushes to GO staging
		break;
	case 'paidcontent': // pC staging
	case 'gostage': // pC staging
		include_once __DIR__ .'/components/class-go-xpost-paidcontent.php';
		$goxpost = new GO_XPost_PaidContent( 'http://gigaom.wp.gostage.it/wp-admin/admin-ajax.php' ); // Pushes to GO staging
		break;
	case 'gigaomstaging': // GO staging on WordPress.com
		include_once __DIR__ .'/components/class-go-xpost-paidcontent.php';
		$goxpost = new GO_XPost_PaidContent( 'http://gigaompaidcontentstaging.wordpress.com/wp-admin/admin-ajax.php' ); // Pushes to GO staging
		break;
	case 'core': // Casey's GO staging
		include_once __DIR__ .'/components/class-go-xpost-gigaom.php';
		$goxpost = new GO_XPost_GigaOM( 'http://pc.gomcom.arcgate.gostage.it/wp-admin/admin-ajax.php' ); // Pushes to pC staging
		break;
	case 'gigaom': // GO staging
		include_once __DIR__ .'/components/class-go-xpost-gigaom.php';
		$goxpost = new GO_XPost_GigaOM( 'http://gostage.paidcontent.org/wp-admin/admin-ajax.php' ); // Pushes to pC staging
		break;

	// Live sites
	case 'gigaom2': // GigaOM.com
		include_once __DIR__ .'/components/class-go-xpost-gigaom.php';
		$goxpost = new GO_XPost_GigaOM( 'http://gigaompaidcontent.wordpress.com/wp-admin/admin-ajax.php' ); // Pushes to paidContent.org
		break;
	case 'gigaompaidcontent': // paidContent.org
		include_once __DIR__ .'/components/class-go-xpost-paidcontent.php';
		$goxpost = new GO_XPost_PaidContent( 'http://gigaom2.wordpress.com/wp-admin/admin-ajax.php' ); // Pushes to GigaOM.com
		break;
}//end switch
