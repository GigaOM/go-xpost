<?php

class GO_XPost
{
	public $filters = array();
	public $slug    = 'go-xpost';
	public $secret;

	public function __construct()
	{
		if ( is_admin() )
		{
			require __DIR__ . '/class-go-xpost-admin.php';
			go_xpost_admin();

			$this->load_filters();
			$this->secret = $this->get_secret();

			add_action( 'edit_post', array( $this, 'edit_post' ) );
			// hook to the receive push to remove the edit_post action
			add_action( 'go_xpost_receive_ping', array( $this, 'receive_ping' ) );

			// hook the utility functions in GO_XPost_Utilities to admin ajax requests
			add_action( 'wp_ajax_go_xpost_pull', array( go_xpost_util(), 'send_post' ) );
			add_action( 'wp_ajax_nopriv_go_xpost_pull', array( go_xpost_util(), 'send_post' ) );

			add_action( 'wp_ajax_go_xpost_ping', array( go_xpost_util(), 'receive_ping' ));
			add_action( 'wp_ajax_nopriv_go_xpost_ping', array( go_xpost_util(), 'receive_ping' ) );
		}// end if

		// Filter comment counts for crossposted content.
		add_filter( 'get_comments_number', array( $this, 'get_comments_number' ), 10, 2 );
	}//end __construct

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
				'secret' => '',
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
		if ( ! isset( $this->filters[$filter] ) )
		{
			include_once dirname( __DIR__ ) . '/filters/class-go-xpost-filter-' . $filter . '.php';
			$classname = 'GO_XPost_Filter_' . ucfirst( $filter );

			if ( class_exists( $classname ) )
			{
				$this->filters[$filter] = new $classname;
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
