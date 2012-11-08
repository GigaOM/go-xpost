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

global $goxpost;

// load the business logic we're using and configure
require_once __DIR__ . '/local/class-go-xpost-' .  go_channel()->go_xpost_config .'.php';
$go_xpost_class_name = 'GO_XPost_' . ucfirst( go_channel()->go_xpost_config );
$goxpost = new $go_xpost_class_name( go_channel()->go_xpost_config_url );
unset( $go_xpost_class_name );
