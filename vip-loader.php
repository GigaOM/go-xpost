<?php

require_once __DIR__ . '/components/class-go-xpost-migrator.php';
require_once __DIR__ . '/components/class-go-xpost.php';

$config = go_config()->load('go-xpost');

// Load appropriate filters for this property
require_once __DIR__ . '/local/class-go-xpost-' . $config['property'] . '.php';

// Instantiate the class
global $goxpost;
$filter_class = 'GO_XPost_' . ucfirst( $config['property'] );
$goxpost = new $filter_class( $config );