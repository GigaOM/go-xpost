<?php

/**
 * this class can never be directly instantiated, use one of it's children
 */
class GO_XPost
{
	protected $post_types = array( 'post' );

	public $filters = array();
	public $slug    = 'go-xpost';
	public $secret;

	public function __construct()
	{
		if ( is_admin() )
		{
			require __DIR__ . '/class-go-xpost-admin.php';
			go_xpost_admin();
		}// end if

		$this->load_filters();
		$this->secret = $this->get_secret();

		// @TODO: these need to be defined in the filters?
		$this->post_types = ( isset( $config['post_types'] ) ) ? array_merge( $config['post_types'], $this->post_types ) : $this->post_types;

		add_action( 'edit_post', array( $this, 'edit_post' ) );
		// hook to the receive push to remove the edit_post action
		add_action( 'go_xpost_receive_push', array( $this, 'receive_push' ) );

		// hook the utility functions in GO_XPost_Utilities to admin ajax requests
		add_action( 'wp_ajax_go_xpost_pull', array( go_xpost_util(), 'send_post' ) );
		add_action( 'wp_ajax_nopriv_go_xpost_pull', array( go_xpost_util(), 'send_post' ) );

		add_action( 'wp_ajax_go_xpost_push', array( go_xpost_util(), 'receive_push' ));
		add_action( 'wp_ajax_nopriv_go_xpost_push', array( go_xpost_util(), 'receive_push' ) );
	}//end __construct

	/**
	 * hook to the edit_post action, possibly ping other sites with the change
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

		// only act on whitelisted post types
		if ( ! in_array( get_post( $post_id )->post_type, $this->post_types ) )
		{
			return;
		}//end if

		// don't attempt to crosspost a crosspost
		if ( (bool) get_post_meta( $post_id, 'go_mancross_redirect', TRUE ) )
		{
			return;
		}//end if

		$this->process_post( $post_id );
	}//end edit_post

	/**
	 * Helper function for edit_post, loops over filters and calls Go_XPost_Utilities::push()
	 */
	protected function process_post( $post_id )
	{
		// In some cases we may want to receive xposts but not send them
		if ( FALSE == $this->filters )
		{
			return;
		}// end if

		// Loop through filters and push to them if appropriate
		foreach ( $this->filters as $filter_name => $filter )
		{
			if ( empty( $filter->endpoint ) )
			{
				continue;
			}
			
			if ( $filter->should_send_post( $post_id ) )
			{
				// log that we are xposting
				apply_filters( 'go_slog', 'go-xpost-start', 'XPost from ' . site_url() . ' to ' . $filter->endpoint . ': START!',
					array(
						'post_id'   => $post_id,
						'post_type' => get_post( $post_id )->post_type,
					)
				);

				go_xpost_util()->push( $filter->endpoint, $post_id, $this->secret, $filter_name );
			}// end if
		} // END foreach
	} // END process_post

	/**
	 * Remove the edit_post action when receiving a post
	 */
	public function receive_push( $post )
	{
		// Remove edit_post action so we don't trigger an accidental crosspost
		remove_action( 'edit_post', array( $this, 'edit_post' ) );
	}// end receive_push

	/**
	 * Get settings for the plugin
	 */
	public function get_settings()
	{
		$default = array(
			array(
				'filter'   => '',
				'endpoint' => '',
				'secret' => '',
			)
		);

		return get_option( $this->slug . '-settings', $default );
	} // END get_settings

	/**
	 * Get the secret for this site
	 */
	public function get_secret()
	{
		return get_option( $this->slug . '-secret' );
	} // END get_secret

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
	 */
	public function load_filter( $filter )
	{
		if ( ! isset( $this->filters[$filter] ) )
		{
			include_once dirname( __DIR__ ) . '/filters/class-go-xpost-filter-' . $filter . '.php';
			$classname = 'GO_XPost_Filter_' . ucfirst( $filter );
			$this->filters[$filter] = new $classname;
		}
	} // END load_filter
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