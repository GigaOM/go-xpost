<?php

class GO_XPost_Admin
{
	public $name       = 'GigaOM xPost';
	public $short_name = 'GO xPost';
	public $slug       = 'go-xpost';

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'update_settings' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'wp_ajax_go_xpost_update_settings', array( $this, 'update_settings' ) );
	}// end __construct

	public function init()
	{
		wp_enqueue_style( $this->slug . '-css', plugins_url( '/css/go-xpost.css', __FILE__ ) );
		wp_enqueue_script( $this->slug . '-js', plugins_url( '/js/go-xpost.js', __FILE__ ), array( 'jquery' ) );
	} // END init

	public function admin_menu()
	{
		add_options_page( $this->name . ' Settings', $this->short_name . ' Settings', 'manage_options', $this->slug . '-settings', array( $this, 'settings' ) );
	} // END admin_menu

	public function settings()
	{
		if ( ! current_user_can( 'manage_options' ) )
		{
			return;
		}// end if

		$settings = go_xpost()->get_settings();
		$secret   = go_xpost()->get_secret();
		
		$filters  = $this->_get_filters();

		$add_link = '<a href="#add-endpoint" title="Add Filter/Endpoint" class="' . $this->slug . '-add-endpoint button">Add Endpoint</a>';
		?>
		<!-- This is for the Add button so it has a template to work off of. -->
		<li style="display: none;" class="<?php echo $this->slug; ?>-setting-template">
			<a href="#remove-endpoint" title="Remove Filter/Endpoint" class="<?php echo $this->slug; ?>-delete-endpoint">Delete</a>
			<div class="<?php echo $this->slug; ?>-filter">
				<label for="<?php echo $this->slug; ?>-filter-keynum"><strong>Filter</strong></label><br />
				<select name='<?php echo $this->slug; ?>-filter-keynum' class='select' id="<?php echo $this->slug; ?>-filter-keynum">
					<?php echo $this->_build_options( $filters, ''); ?>
				</select>
			</div>
			<div class="<?php echo $this->slug; ?>-endpoint">
				<label for="<?php echo $this->slug; ?>-endpoint-keynum"><strong>Endpoint</strong></label><br />
				<input class="input" type="text" name="<?php echo $this->slug ;?>-endpoint-keynum" id="<?php echo $this->slug; ?>-endpoint-keynum" value="" placeholder="http://domain/wp-admin/admin-ajax.php" />
			</div>
			<input type="hidden" name="<?php echo $this->slug; ?>-number-keynum" value="keynum" class="number" />
		</li>
		<div class="wrap">
			<?php
			if ( isset( $_POST['updated'] ) )
			{
				?>
				<br />
				<div id="go-xpost-settings-updated" class="updated fade">
					<p><strong>Settings updated.</strong></p>
				</div>
				<?php
			}// end if
			?>
			<?php screen_icon('options-general'); ?>
			<?php echo $add_link; ?>
			<h2><?php echo $this->name ?> Settings</h2>
			<form method="post">
				<ul class="<?php echo $this->slug; ?>-settings">
					<?php
					$setting_numbers = '';

					foreach ( $settings as $key => $item )
					{
						$setting_numbers .= $key + 1 . ',';
						?>
						<li>
							<a href="#remove-endpoint" title="Remove Filter/Endpoint" class="<?php echo $this->slug; ?>-delete-endpoint">Delete</a>
							<div class="<?php echo $this->slug; ?>-filter">
								<label for="<?php echo $this->slug; ?>-filter-<?php echo $key + 1; ?>"><strong>Filter</strong></label><br />
								<select name='<?php echo $this->slug; ?>-filter-<?php echo $key + 1; ?>' class='select' id="<?php echo $this->slug; ?>-filter-<?php echo $key + 1; ?>">
									<?php echo $this->_build_options( $filters, $item['filter'] ); ?>
								</select>
							</div>
							<div class="<?php echo $this->slug; ?>-endpoint">
								<label for="<?php echo $this->slug; ?>-endpoint-<?php echo $key + 1; ?>"><strong>Endpoint</strong></label><br />
								<input class="input" type="text" name="<?php echo $this->slug; ?>-endpoint-<?php echo $key + 1; ?>" id="<?php echo $this->slug; ?>-endpoint-<?php echo $key + 1; ?>" value="<?php echo esc_attr($item['endpoint']); ?>" placeholder="http://domain/wp-admin/admin-ajax.php" />
							</div>
							<input type="hidden" name="<?php echo $this->slug; ?>-number-<?php echo $key + 1; ?>" value="<?php echo $key + 1; ?>" class="number" />
						</li>
						<?php
					} // END foreach
					?>
				</ul>

				<div class="<?php echo $this->slug; ?>-secret">
					<label for="<?php echo $this->slug; ?>-secret"><strong>Shared Secret</strong></label><br />
					<input class="input" type="text" name="<?php echo $this->slug; ?>-secret" id="<?php echo $this->slug; ?>-secret" value="<?php echo esc_attr( $secret ); ?>" placeholder="Something complex..." /><br />
					<em>Secret that is shared between all of the sites being xPosted to/from.</em>
				</div>	

				<p class="submit">
					<?php wp_nonce_field( 'save-' . $this->slug . '-settings' ); ?>
					<input type="hidden" name="setting-numbers" class="setting-numbers" value="<?php echo substr( $setting_numbers, 0, -1 ); ?>" />
					<input type="submit" class="button button-primary" name="save-<?php echo $this->slug; ?>-settings" value="Save Changes" />
				</p>
			</form>
		</div>
		<?php
	} // END settings

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

		$numbers_array = explode( ',', $_POST['setting-numbers'] );

		$compiled_settings = array();

		foreach ( $numbers_array as $number )
		{
			if ( isset( $_POST[$this->slug . '-endpoint-' . $number] ) && preg_match( '#((https?)\:\/\/)#', $_POST[$this->slug . '-endpoint-' . $number] ) )
			{
				$compiled_settings[] = array(
					'filter'   => $_POST[ $this->slug . '-filter-' . $number ],
					'endpoint' => $_POST[ $this->slug . '-endpoint-' . $number ],
					//'secret'   => $_POST[ $this->slug . '-secret-' . $number ],
				);
			}// end if
		} // END foreach

		update_option( $this->slug . '-settings', $compiled_settings );
		update_option( $this->slug . '-secret', $_POST[ $this->slug . '-secret' ] );
		$_POST['updated'] = TRUE;
	} // END update_settings

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
			if ( preg_match( '|Filter Name:(.*)$|mi', $template_data, $name ))
			{
				// remove the class-go-xpost-filter portion of the file
				$filter = substr( basename( $file, '.php' ), 22 );

				$filters[$filter] = $filter . ' - ' . _cleanup_header_comment( $name[1] );
			}// end if
		}// end foreach

		return $filters;
	} // END _get_filters

	private function _build_options( $options, $existing )
	{
		$select_options = '';

		foreach ( $options as $option => $text )
		{
			$select_options .= '<option value="' . $option . '"' . selected( $option, $existing, FALSE ) . '>' . $text . '</option>' . "\n";
		}// end foreach

		return $select_options;
	}// END _build_options
}//end GO_XPost_Admin

function go_xpost_admin()
{
	global $go_xpost_admin;

	if ( ! isset( $go_xpost_admin ) )
	{
		$go_xpost_admin = new GO_XPost_Admin();
	}// end if

	return $go_xpost_admin;
}// end go_xpost_admin