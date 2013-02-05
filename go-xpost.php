<?php
/**
 * Plugin Name: GigaOM xPost
 * Plugin URI: http://gigaom.com
 * Description: Pings another domain to let it know a new relevant post has been added which
 *              triggers that domain to then pull the data it needs to replicate the post in WP.
 * Author: GigaOM
 * Version: 1.0
 * Author URI: http://gigaom.com/
 */

require_once __DIR__ . '/components/class-go-xpost-migrator.php';
require_once __DIR__ . '/components/class-go-xpost.php';

global $goxpost;

$pro_config = array(
	// The current property that we are currently in
	'property'  => 'pro',
	// Properties and their endpoints that we wish to push to
	'endpoints' => array(
		'gigaom' => 'http://go.localhost/wp-admin/admin-ajax.php',
		'search' => 'http://search.localhost/wp-admin/admin-ajax.php',
	),
	// Post types that we want to push
	'post_types' => array(
		'post',
		'go_shortpost',
		'go-datamodule',
	),
);

$goxpost = new GO_XPost( go_config()->load('go-xpost') );