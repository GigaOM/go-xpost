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

require_once __DIR__ . '/components/class-go-xpost.php';
require_once __DIR__ . '/components/class-go-xpost-utilities.php';
require_once __DIR__ . '/filters/class-go-xpost-filter.php';

go_xpost();