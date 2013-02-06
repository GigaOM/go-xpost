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

$config = go_config()->load('go-xpost');

// Load appropriate filters for this property
require_once __DIR__ . '/local/class-go-xpost-' . $config['property'] . '.php';

// Instantiate the class
global $goxpost;
$filter_class = 'GO_XPost_' . ucfirst( $config['property'] );
$goxpost = new $filter_class( $config );