<?php

class GO_XPost
{
	public $admin   = FALSE;
	public $cron    = FALSE;
	public $filters = array();
	public $slug    = 'go-xpost';
	public $secret  = NULL;
	public $config  = NULL;

	public function __construct()
	{
		add_action( 'edit_post', array( $this, 'edit_post' ) );

		if ( $this->config()->cron_interval )
		{
			$this->cron();
		} // END if

		if ( is_admin() )
		{
			$this->admin();
		}

		// Filter comment counts for crossposted content.
		add_filter( 'get_comments_number', array( $this, 'get_comments_number' ), 10, 2 );

		// hook this action to update crossposted comment count after WP sets that
		// number, else the xpost'ed comment count will always get overwritten by WP
		add_action( 'wp_update_comment_count', array( go_xpost_util(), 'update_comment_count' ), 10, 3 );

		// If wp-cli is active load the xpost additions
		if ( defined( 'WP_CLI' ) && WP_CLI )
		{
			include __DIR__ . '/class-go-xpost-wp-cli.php';
		}
	}//end __construct

	public function config()
	{
		if ( ! $this->config )
		{
			$default_config = array(
				'slog_get_author' => FALSE,
				// Set to FALSE to turn off cron xposting
				'cron_interval'  => 5, // Minutes
				// List of post types to xPost via cron
				'cron_post_types' => array(
					'post',
				),
				// Term used for checking if a post has already xPosted
				'cron_term' => 'posted',
				// Number of posts to attempt to xPost each time the cron is run
				'cron_limit' => 10,
			);

			$this->config = (object) apply_filters( 'go_config', $default_config, 'go-xpost' );
		} // END if

		return $this->config;
	} // END config

	public function cron()
	{
		if ( ! $this->cron )
		{
			require_once __DIR__ . '/class-go-xpost-cron.php';
			$this->cron = new GO_XPost_Cron();
		} // END if

		return $this->cron;
	} // END cron

	public function admin()
	{
		if ( ! $this->admin )
		{
			require __DIR__ . '/class-go-xpost-admin.php';
			$this->admin = new GO_XPost_Admin();

			$this->load_filters();
			$this->secret = $this->get_secret();

			// hook to the receive push to remove the edit_post action
			add_action( 'go_xpost_receive_ping', array( $this, 'receive_ping' ) );

			// hook the utility functions in GO_XPost_Utilities to admin ajax requests
			add_action( 'wp_ajax_go_xpost_pull', array( go_xpost_util(), 'send_post' ) );
			add_action( 'wp_ajax_nopriv_go_xpost_pull', array( go_xpost_util(), 'send_post' ) );

			add_action( 'wp_ajax_go_xpost_ping', array( go_xpost_util(), 'receive_ping' ));
			add_action( 'wp_ajax_nopriv_go_xpost_ping', array( go_xpost_util(), 'receive_ping' ) );
		} // END if

		return $this->admin;
	}//END admin

	/**
	 * hook to the edit_post action, possibly ping other sites with the change
	 *
	 * @param $post_id int wordpress $post_id to edit
	 */
	public function edit_post( $post_id )
	{
		// don't bother with autosaves
		if ( defined( 'DOING_AUTOSAVE' ) )
		{
			return;
		}//end if

		// check the post_id
		$post_id = go_xpost_util()->sanitize_post_id( $post_id );

		if ( ! $post_id )
		{
			return;
		}//end if

		// make sure we have an admin object which handles the admin ajax
		// calls involved in xposting btw the source and endpoint blogs
		$this->admin();

		$this->process_post( $post_id );
	}//end edit_post

	/**
	 * Helper function for edit_post, loops over filters and calls Go_XPost_Utilities::ping()
	 *
	 * @param $post_id int wordpress $post_id to edit
	 */
	public function process_post( $post_id )
	{
		// In some cases we may want to receive xposts but not send them
		if ( FALSE == $this->filters )
		{
			return;
		}// end if

		// don't attempt to crosspost a crosspost
		if ( go_xpost_redirect()->is_xpost( $post_id ) )
		{
			return;
		}//end if

		$site_url_host = parse_url( site_url(), PHP_URL_HOST );
		$home_url_host = parse_url( home_url(), PHP_URL_HOST );

		// Loop through filters and push to them if appropriate
		foreach ( $this->filters as $filter_name => $filter )
		{
			if ( empty( $filter->endpoint ) )
			{
				continue;
			}// end if

			// if the configured endpoint hostname matches the site_url, we should not xpost to there
			$filter_host = parse_url( $filter->endpoint, PHP_URL_HOST );
			if ( $filter_host === $site_url_host || $filter_host === $home_url_host )
			{
				// log that we have a bad endpoint configured
				apply_filters( 'go_slog', 'go-xpost-bad-endpoint', 'XPost from ' . site_url() . ' to ' . $filter->endpoint . ': Bad endpoint!',
					array(
						'post_id'   => $post_id,
					)
				);
				return;
			}// end if

			if ( $filter->should_send_post( $post_id ) )
			{
				// log that we are xposting
				apply_filters( 'go_slog', 'go-xpost-start', 'XPost from ' . site_url() . ' to ' . $filter->endpoint . ': START!',
					array(
						'post_id'   => $post_id,
						'post_type' => get_post( $post_id )->post_type,
					)
				);

				go_xpost_util()->ping( $filter->endpoint, $post_id, $this->secret, $filter_name );
			}// end if
		} // END foreach
	} // END process_post

	/**
	 * Remove the edit_post action when receiving a ping
	 *
	 * @param $post WP_Post object
	 */
	public function receive_ping( $post )
	{
		// Remove edit_post action so we don't trigger an accidental crosspost
		remove_action( 'edit_post', array( $this, 'edit_post' ) );
	}// end receive_push

	/**
	 * Get settings for the plugin
	 *
	 * @return get_option: Mixed values for the option. If option does not exist, return boolean FALSE.
	 */
	public function get_settings()
	{
		$default = array(
			array(
				'filter'   => '',
				'endpoint' => '',
				'secret'   => '',
			),
		);

		return get_option( $this->slug . '-settings', $default );
	} // END get_settings

	/**
	 * Get the secret for this site
	 *
	 * @return  Mixed values for the option. If option does not exist, return boolean FALSE.
	 */
	public function get_secret()
	{
		return get_option( $this->slug . '-secret' );
	} // END get_secret

	/**
	 * Get the request method (GET or POST) for this site
	 *
	 * @return  Mixed values for the option. If option does not exist, return boolean FALSE.
	 */
	public function get_request_method()
	{
		$method = get_option( $this->slug . '-method' );
		if ( ! $method )
		{
			$method = 'GET';
		}// end if
		return $method;
	} // END get_request_method

	/**
	 * Load the filters as defined in settings, will instantiate objects for each
	 */
	private function load_filters()
	{
		$settings = $this->get_settings();

		foreach ( $settings as $setting )
		{
			if ( $setting['filter'] )
			{
				$this->load_filter( $setting['filter'] );
				$this->filters[$setting['filter']]->endpoint = $setting['endpoint'];
			}// end if
		}// end foreach
	}// end load_filters

	/**
	 * Load a single filter
	 *
	 * @param $filter wordpress $filter to load
	 */
	public function load_filter( $filter )
	{
		if ( ! isset( $this->filters[ $filter ] ) )
		{
			include_once dirname( __DIR__ ) . '/filters/class-go-xpost-filter-' . $filter . '.php';
			$classname = 'GO_XPost_Filter_' . ucfirst( $filter );

			if ( class_exists( $classname ) )
			{
				$this->filters[ $filter ] = new $classname;
			}//end if
		}// end if
	} // END load_filter

	/**
	 * Filter get_comments_number value for crossposted content
	 */
	public function get_comments_number( $count, $post_id )
	{
	 	if ( $xpost_count = get_post_meta( $post_id, 'go_xpost_comment_count', TRUE ) )
	 	{
	 		return $xpost_count;
	 	} // END if

		return $count;
	} // END get_comments_number
}//end class

function go_xpost()
{
	global $go_xpost;

	if ( ! isset( $go_xpost ) )
	{
		$go_xpost = new GO_XPost();
	}// end if

	return $go_xpost;
}// end go_xpost
