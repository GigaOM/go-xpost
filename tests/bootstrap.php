<?php
// Load WordPress test environment
// https://github.com/nb/wordpress-tests
//

// get the path to wordpress-tests' bootstrap.php file from the environment
$bootstrap = getenv( 'WPT_BOOTSTRAP' );
if ( FALSE === $bootstrap )
{
	echo "\n!!! Please set the WPT_BOOTSTRAP env var to point to your\n!!! wordpress-tests/includes/bootstrap.php file.\n\n";
	return;
}

if ( file_exists( $bootstrap ) )
{
	$GLOBALS['wp_tests_options'] = array(
		'pro' => TRUE,
		'active_plugins' => array(
			'co-authors-plus/co-authors-plus.php',
			'go-xpost/go-xpost.php',
			'go-local-xpost/go-local-xpost.php',
		),
		'template' => 'gigaom-pro',
		'stylesheet' => 'gigaom-pro',
	);

	require_once $bootstrap;

	// make sure the go-config dir is set
	update_option( 'go-config-dir', '_pro' );
}//END if
else
{
    exit( "Couldn't find path to $bootstrap\n" );
}//END else
