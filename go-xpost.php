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

$config = array(
	// The property that we are currently in
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

// Load appropriate filters for this property
require_once __DIR__ . '/local/class-go-xpost-' . $config['property'] . '.php';

// Instantiate the class
global $goxpost;
$filter_class = 'GO_XPost_' . ucfirst( $config['property'] );
$goxpost = new $filter_class( $config );