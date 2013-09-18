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
			'buddypress/bp-loader.php',
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

	// make sure the full name is labeled as: Full Name
	update_option( 'bp-xprofile-fullname-field-name', 'Full Name' );

	activate_plugin( WP_CONTENT_DIR . '/plugins/buddypress/bp-loader.php', '', TRUE, TRUE );

	$active_components = array(
		'activity' => TRUE,
		'messages' => TRUE,
		'xprofile' => TRUE,
		'blogs' => TRUE,
	);

	update_option( 'bp-active-components', $active_components );

	require_once WP_CONTENT_DIR . '/plugins/buddypress/bp-core/admin/bp-core-schema.php';
	bp_core_install();

}//END if
else
{
    exit( "Couldn't find path to $bootstrap\n" );
}//END else

// make sure buddypress' admin_init action is called so it can create
// the db tables it needs
//do_action( 'admin_init' );
//bp_core_admin_components_settings_handler();