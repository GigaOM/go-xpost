<?php

class GO_XPost_Redirect
{
	public $meta_key = 'go_xpost_redirect';
	
	public function __construct()
	{
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'init', array( $this, 'init' ) );
	}//end __construct

	/**
	 * uses the admin_init hook to initialize admin pieces
	 */
	public function admin_init()
	{
		if( current_user_can( 'edit_others_posts' ) || current_user_can( 'edit_others_pages' ) )
		{
			add_meta_box( $this->meta_key . '_meta_box', 'Redirection', array( $this, 'meta_box' ), 'post', 'advanced', 'high' );
			add_meta_box( $this->meta_key . '_meta_box', 'Redirection', array( $this, 'meta_box' ), 'page', 'advanced', 'high' );
			
			add_action( 'save_post', array( $this, 'save_post' ), 2 );
			add_filter( 'display_post_states', array( $this, 'display_post_states' ) );
		}//end if
	}//end admin_init

	/**
	 * Customize the post status for cross posts so you can see an article is an xPost from the posts page
	 *
	 * @param $states array Array of post states
	 */
	public function display_post_states( $states )
	{
		if( $this->is_xpost() )
		{
			$states[] = 'xPost';
		}//end if

		return $states;
	}//end display_post_states

	/**
	 * Uses the init hook to initialize shizzle
	 */
	public function init()
	{
		add_filter( 'post_link', array( $this, 'post_link' ), 11, 2 );
		add_filter( 'post_type_link', array( $this, 'post_link' ), 11, 2 );
		add_filter( 'template_redirect', array( $this, 'template_redirect' ), 1 );
		add_filter( 'sitemap_skip_post', array( $this, 'sitemap_skip_post' ) );
	}//end init

	/**
	 * Indicate whether or not a post is a cross post
	 *
	 * @param $post_id int Post ID
	 */
	public function is_xpost( $post_id = 0 )
	{
		$post_id = ( $post_id ) ? (int) $post_id : get_the_ID();
		return (bool) $this->get_post_meta( $post_id, 'go_mancross_redirect' );
	} // END is_xpost

	/**
	 * Dump out a metabox for specifying cross post status
	 */
	public function meta_box()
	{
		global $post;
		
		$redirect = $this->get_post_meta( $post->ID );
		$checked  = ( $redirect ) ? TRUE : FALSE;
		?>
		<p>
			<input type="checkbox" name="<?php echo $this->meta_key; ?>_x" id="<?php echo $this->meta_key; ?>_x" value="1"<?php checked( $checked ); ?> /> <label for="<?php echo $this->meta_key; ?>_x">Redirect this post?</label>
		</p>
		<p class="target_url">
			<label for="<?php echo $this->meta_key; ?>"><strong>Target URL</strong></label><br />
			<input type="text" name="<?php echo $this->meta_key; ?>" id="<?php echo $this->meta_key; ?>" value="<?php echo $redirect; ?>" placeholder="http://path/to/original/post/" class="widefat" />
		</p>
		<?php
		echo '<input type="hidden" name="' . $this->meta_key . '_nonce" id="' . $this->meta_key . '_nonce" value="' . wp_create_nonce( __FILE__ ) . '" />';
	}//end meta_box

	/**
	 * When saving a post, make sure the xPost stuff is saved too
	 *
	 * @param $post_id int Post ID
	 */
	public function save_post( $post_id )
	{
		// Check that this isn't an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		{
			return;
		}// end if

		$post = get_post( $post_id );
		if ( ! is_object( $post ) )
		{
			return;
		}// end if

		// check post type matches what you intend
		// We're using go-manual-cross for a bunch of post types, but we're actually only putting the metabox to edit this value on post.
		$whitelisted_post_types = array(
			'post', 
			'page'
		);
		
		if ( ! isset( $post->post_type ) || ! in_array( $post->post_type, $whitelisted_post_types ) )
		{
			return;
		}// end if

		// Don't run on post revisions (almost always happens just before the real post is saved)
		if ( wp_is_post_revision( $post->ID ) )
		{
			return;
		}// end if

		// Check the nonce
		if ( ! isset( $_POST[$this->meta_key . '_nonce'] ) || ! wp_verify_nonce( $_POST[$this->meta_key . '_nonce'], __FILE__ ) )
		{
			return $post_id;
		}// end if

		// Check the permissions
		if ( ! current_user_can( 'edit_post', $post->ID  ) )
		{
			return;
		}// end if

		$redirect = esc_url_raw( $_POST[$this->meta_key] );

		if ( is_null( $redirect ) || ! isset( $_POST[$this->meta_key . '_x'] ) )
		{
			delete_post_meta( $post_id, $this->meta_key );
		}//end if
		elseif ( ! is_null( $redirect ) && isset( $_POST[$this->meta_key . '_x'] ) )
		{
			$this->update_post_meta( $post_id, $redirect );
		}//end elseif

		return $post_id;
	}//end save_post

	/**
	 * Return an appropriate link for the post.  xPost redirect, or permalink.
	 *
	 * @param $permalink string Post permalink
	 * @param $post WP_Post Post object
	 */
	public function post_link( $permalink, $post )
	{
		if ( $redirect = $this->get_post_meta( $post->ID ) )
		{
			return $redirect;
		}//end if
		
		return $permalink;
	}//end post_link

	/**
	 * Make sure the sitemap generation skips cross posts
	 *
	 * @param $skip
	 * @param $post WP_Post Post object
	 */
	public function sitemap_skip_post( $skip, $post )
	{
		if ( $this->is_xpost( $post->ID ) )
		{
			return TRUE;
		}//end if

		return $skip;
	}//end sitemap_skip_post

	/**
	 * Redirect the user to the xPost redirect url if the post is a xPost
	 */
	public function template_redirect()
	{
		global $wp_query;

		if ( $wp_query->is_singular && $redirect = $this->get_post_meta( $wp_query->queried_object->ID ) )
		{
			wp_redirect( $redirect, '301' );
			exit;
		}//end if
	}//end template_redirect

	/**
	 * Update the redirect value
	 *
	 * @param $post_id int Post ID
	 * @param $redirect str redirect url for the post
	 */
	public function update_post_meta( $post_id, $redirect )
	{
		update_post_meta( $post_id, $this->meta_key, $redirect );
	} // END update_post_meta

	/**
	 * Return the redirect value if it exists and upgrade historical meta to new if needed
	 *
	 * @param $post_id int Post ID
	 */
	public function get_post_meta( $post_id )
	{
		if ( ! $redirect =  get_post_meta( $post_id, $this->meta_key, TRUE ) )
		{
			// Check for old go_mancross_redirect meta and upgrade if it has a value
			if ( $redirect = get_post_meta( $post_id, 'go_mancross_redirect', TRUE ) )
			{
				$this->update_post_meta( $post_id, $redirect );
				delete_post_meta( $post_id, 'go_mancross_redirect' );
			} // END if
		} // END if
		
		return $redirect;
	} // END get_post_meta
} // END GO_XPost_Redirect

function go_xpost_redirect()
{
	global $go_xpost_redirect;

	if ( ! isset( $go_xpost_redirect ) )
	{
		$go_xpost_redirect = new GO_XPost_Redirect();
	}// end if

	return $go_xpost_redirect;
}// end go_xpost_redirect