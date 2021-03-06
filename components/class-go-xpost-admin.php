<?php

class GO_XPost_Admin
{
	public $name       = 'Gigaom xPost';
	public $short_name = 'GO xPost';
	public $slug       = 'go-xpost';

	public function __construct()
	{
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'wp_ajax_go_xpost_update_settings', array( $this, 'update_settings' ) );
		add_action( 'wp_ajax_go_xpost_batch', array( $this, 'batch' ) );
		add_action( 'wp_ajax_go_xpost_register_cron', array( $this, 'register_cron' ) );
	}// end __construct

	public function admin_init()
	{
		$js_min = ( defined( 'GO_DEV' ) && GO_DEV ) ? 'lib' : 'min';

		wp_enqueue_style( $this->slug . '-css', plugins_url( '/css/go-xpost.css', __FILE__ ) );
		wp_enqueue_script( $this->slug . '-js', plugins_url( '/js/' . $js_min . '/go-xpost.js', __FILE__ ), array( 'jquery' ) );

		$this->update_settings();
	} // end admin_init

	public function admin_menu()
	{
		add_options_page( $this->name . ' Settings', $this->short_name . ' Settings', 'manage_options', $this->slug . '-settings', array( $this, 'settings' ) );
	} // end admin_menu

	public function settings()
	{
		if ( ! current_user_can( 'manage_options' ) )
		{
			return;
		}// end if

		$settings = go_xpost()->get_settings();
		$secret   = go_xpost()->get_secret();
		$verbose_log = go_xpost()->verbose_log();
		$method   = go_xpost()->get_request_method();
		$method   = ! $method ? 'GET' : $method;

		$filters  = $this->_get_filters();

		require __DIR__ . '/templates/settings.php';
	} // end settings

	public function update_settings()
	{
		// Check nonce
		if ( ! isset( $_POST['save-' . $this->slug . '-settings'] ) || ! check_admin_referer( 'save-' . $this->slug . '-settings' ) )
		{
			return;
		}// end if

		if ( ! current_user_can( 'manage_options' ) )
		{
			return;
		}// end if

		$numbers_array = explode( ',', $_POST[ $this->slug . '-setting-numbers' ] );

		$compiled_settings = array();

		foreach ( $numbers_array as $number )
		{
			if ( isset( $_POST[ $this->slug . '-endpoint-' . $number ] ) && preg_match( '#((https?)\:\/\/)#', $_POST[ $this->slug . '-endpoint-' . $number ] ) )
			{
				$compiled_settings[] = array(
					'filter'   => $_POST[ $this->slug . '-filter-' . $number ],
					'endpoint' => $_POST[ $this->slug . '-endpoint-' . $number ],
				);
			}// end if
		} // end foreach

		update_option( $this->slug . '-settings', $compiled_settings );
		update_option( $this->slug . '-secret', $_POST[ $this->slug . '-secret' ] );
		update_option( $this->slug . '-verbose-log', (bool) $_POST[ $this->slug . '-verbose-log' ] );
		update_option( $this->slug . '-method', $_POST[ $this->slug . '-method' ] );

		$_POST['updated'] = TRUE;
	} // end update_settings

	private function _get_filters()
	{
		$directory_contents = new DirectoryIterator( dirname( __DIR__ ) . '/filters/' );

		$filters = array();

		foreach ( $directory_contents as $file )
		{
			if ( ! $file->isFile() || 'php' != $file->getExtension() )
			{
				continue;
			}// end if

			$template_data = implode( '', file( $file->getPathname() ) );

			$name = '';

			// only load filters that have names, this will skip the abstract parent
			if ( preg_match( '|Filter Name:(.*)$|mi', $template_data, $name ) )
			{
				// remove the class-go-xpost-filter portion of the file
				$filter = substr( basename( $file, '.php' ), 22 );

				$filters[ $filter ] = $filter . ' - ' . _cleanup_header_comment( $name[1] );
			}// end if
		}// end foreach

		return $filters;
	} // end _get_filters

	private function _build_options( $options, $existing )
	{
		$select_options = '';

		foreach ( $options as $option => $text )
		{
			$select_options .= '<option value="' . $option . '"' . selected( $option, $existing, FALSE ) . '>' . $text . '</option>' . "\n";
		}// end foreach

		return $select_options;
	}// end _build_options

	/**
	 * Hooked to admin ajax request
	 */
	public function batch()
	{
		if ( ! current_user_can( 'manage_options' ) )
		{
			return;
		}// end if

		?><h2><?php echo $this->name ?> Batch xPosting</h2><?php

		$batch_name = sanitize_key( $_GET['batch_name'] );
		$post_types = sanitize_text_field( $_GET['post_types'] );
		$num        = isset( $_GET['num'] ) ? absint( $_GET['num'] ) : 10;
		$posts      = $this->get_posts_to_batch( $batch_name, $post_types, $num );
		$page       = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
		?>
		<h3><?php echo esc_html( $batch_name ); ?></h3>
		<p>Batch <?php echo absint( $page ); ?></p>
		<?php
		if ( ! $posts )
		{
			?>
			<p>No posts found.</p>
			<p><a href="<?php echo admin_url( 'options-general.php?page=go-xpost-settings' ); ?>" onclick="clearTimeout(reloader)">Gigaom xPost Settings</a></p>
			<?php
			die;
		} // END if

		foreach ( $posts as $post )
		{
			if ( ! isset( $post->ID ) )
			{
				continue;
			}// end if

			echo $post->ID . '<br/>';
			go_xpost()->process_post( $post->ID );
			wp_set_post_terms( $post->ID, $batch_name, go_xpost()->cron()->slug, TRUE );

			sleep( 2 );
		}// end foreach

		$args = array(
			'action'     => 'go_xpost_batch',
			'batch_name' => $batch_name,
			'post_types' => $post_types,
			'page'       => $page + 1,
			'num'        => $num,
		);
		?>
		<script>
			var reloader = window.setTimeout(function(){
				window.location = "?<?php echo http_build_query( $args ); ?>";
			}, 5000);
		</script>
		<p><em>Will reload to the next 10, every 5 seconds.</em></p>
		<p>
			  <a href="#stop" onclick="clearTimeout(reloader)">Stop</a>
			| <a href="<?php echo admin_url( 'options-general.php?page=go-xpost-settings' ); ?>" onclick="clearTimeout(reloader)">Gigaom xPost Settings</a>
		</p>
		<?php
		die;
	}// end batch

	/**
	 * get posts for a given batch name
	 *
	 * @param $batch_name string batch name slug
	 * @param $limit int how many do you want?
	 */
	private function get_posts_to_batch( $batch_name, $post_types, $limit = 10 )
	{
		if ( $post_types )
		{
			$post_types = array_map( 'sanitize_key', (array) explode( ',', $post_types ) );
		}// end if
		else
		{
			$post_types = array( 'post' );
		}// end else

		return go_xpost()->cron()->get_posts(
			$post_types,
			sanitize_key( $batch_name ),
			$limit
		);
	}// end get_posts_to_batch

	/**
	 * AJAX endpoint which registers or unregisters our cron hook wtih WordPress
	 */
	public function register_cron()
	{
		go_xpost()->cron()->register_cron();
	} // END register_cron
}//end class
