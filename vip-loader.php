<?php

require_once __DIR__ . '/components/class-go-xpost.php';
require_once __DIR__ . '/components/class-go-xpost-utilities.php';

$config = go_config()->load('go-xpost');
$config = apply_filters( 'go_xpost_config', $config );

// Load appropriate filters for this property
require_once __DIR__ . '/filters/class-go-xpost-' . $config['property'] . '.php';

// Instantiate the class
global $goxpost;
$filter_class = 'GO_XPost_' . ucfirst( $config['property'] );
$goxpost = new $filter_class( $config );
