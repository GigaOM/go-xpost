<!-- This is for the Add button so it has a template to work off of. -->
<li style="display: none;" class="<?php echo esc_attr( $this->slug ); ?>-setting-template">
	<a href="#remove-endpoint" title="Remove Filter/Endpoint" class="<?php echo esc_attr( $this->slug ); ?>-delete-endpoint">Delete</a>
	<div class="<?php echo esc_attr( $this->slug ); ?>-filter">
		<label for="<?php echo esc_attr( $this->slug ); ?>-filter-keynum"><strong>Filter</strong></label><br />
		<select name='<?php echo esc_attr( $this->slug ); ?>-filter-keynum' class='select' id="<?php echo esc_attr( $this->slug ); ?>-filter-keynum">
			<?php echo $this->_build_options( $filters, '' ); ?>
		</select>
	</div>
	<div class="<?php echo esc_attr( $this->slug ); ?>-endpoint">
		<label for="<?php echo esc_attr( $this->slug ); ?>-endpoint-keynum"><strong>Endpoint</strong></label><br />
		<input class="input" type="text" name="<?php echo esc_attr( $this->slug ) ;?>-endpoint-keynum" id="<?php echo esc_attr( $this->slug ); ?>-endpoint-keynum" value="" placeholder="http://domain/wp-admin/admin-ajax.php" />
	</div>
	<input type="hidden" name="<?php echo esc_attr( $this->slug ); ?>-number-keynum" value="keynum" class="number" />
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
	elseif ( isset( $_GET['success'] ) )
	{
		if ( 'registered' == $_GET['success'] )
		{
			?>
			<div id="go-xpost-settings-success" class="updated fade">
				<p><strong>Cron xPosting enabled. </strong></p>
			</div>
			<?php
		} // END if
		elseif ( 'unregistered' == $_GET['success'] )
		{
			?>
			<div id="go-xpost-settings-success" class="updated fade">
				<p><strong>Cron xPosting disabled.</strong></p>
			</div>
			<?php
		} // END elseif
	} // END elseif
	?>
	<?php echo '<a href="#add-endpoint" title="Add Filter/Endpoint" class="' . esc_attr( $this->slug ) . '-add-endpoint button">Add Endpoint</a>'; ?>
	<h2><?php echo $this->name ?> Settings</h2>
	<form method="post">
		<ul class="<?php echo esc_attr( $this->slug ); ?>-settings">
			<?php
			$setting_numbers = '';

			foreach ( $settings as $key => $item )
			{
				$setting_numbers .= $key + 1 . ',';
				?>
				<li>
					<a href="#remove-endpoint" title="Remove Filter/Endpoint" class="<?php echo esc_attr( $this->slug ); ?>-delete-endpoint">Delete</a>
					<div class="<?php echo esc_attr( $this->slug ); ?>-filter">
						<label for="<?php echo esc_attr( $this->slug ); ?>-filter-<?php echo absint( $key ) + 1; ?>"><strong>Filter</strong></label><br />
						<select name='<?php echo esc_attr( $this->slug ); ?>-filter-<?php echo absint( $key ) + 1; ?>' class='select' id="<?php echo esc_attr( $this->slug ); ?>-filter-<?php echo $key + 1; ?>">
							<?php echo $this->_build_options( $filters, $item['filter'] ); ?>
						</select>
					</div>
					<div class="<?php echo esc_attr( $this->slug ); ?>-endpoint">
						<label for="<?php echo esc_attr( $this->slug ); ?>-endpoint-<?php echo absint( $key ) + 1; ?>"><strong>Endpoint</strong></label><br />
						<input class="input" type="text" name="<?php echo esc_attr( $this->slug ); ?>-endpoint-<?php echo absint( $key ) + 1; ?>" id="<?php echo esc_attr( $this->slug ); ?>-endpoint-<?php echo $key + 1; ?>" value="<?php echo esc_attr( $item['endpoint'] ); ?>" placeholder="http://domain/wp-admin/admin-ajax.php" />
					</div>
					<input type="hidden" name="<?php echo esc_attr( $this->slug ); ?>-number-<?php echo absint( $key ) + 1; ?>" value="<?php echo absint( $key ) + 1; ?>" class="number" />
				</li>
				<?php
			} // end foreach
			?>
		</ul>

		<div class="<?php echo esc_attr( $this->slug ); ?>-secret">
			<label for="<?php echo esc_attr( $this->slug ); ?>-secret"><strong>Shared secret</strong></label><br />
			<input class="input" type="text" name="<?php echo esc_attr( $this->slug ); ?>-secret" id="<?php echo esc_attr( $this->slug ); ?>-secret" value="<?php echo esc_attr( $secret ); ?>" placeholder="Something complex..." /><br />
			<em>Secret that is shared between all of the sites being xPosted to/from.</em>
		</div>

		<div class="<?php echo esc_attr( $this->slug ); ?>-method">
			<label for="<?php echo esc_attr( $this->slug ); ?>-method"><strong>Request method</strong></label><br />
			<input type="radio" name="<?php echo esc_attr( $this->slug ); ?>-method" id="<?php echo $this->slug; ?>-method-get" value="GET" <?php checked( $method, 'GET' ); ?>/> GET<br />
			<input type="radio" name="<?php echo esc_attr( $this->slug ); ?>-method" id="<?php echo esc_attr( $this->slug ); ?>-method-get" value="POST" <?php checked( $method, 'POST' ); ?>/> POST<br />
		</div>

		<p class="submit">
			<?php wp_nonce_field( 'save-' . $this->slug . '-settings' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $this->slug ); ?>-setting-numbers" class="<?php echo esc_attr( $this->slug ); ?>-setting-numbers" value="<?php echo substr( $setting_numbers, 0, -1 ); ?>" />
			<input type="submit" class="button button-primary" name="save-<?php echo esc_attr( $this->slug ); ?>-settings" value="Save Changes" />
		</p>
	</form>
	<?php
	if ( go_xpost()->config()->cron_interval )
	{
		?>
		<hr />
		<h3>Cron xPosting</h3>
		<p><em>Enables/Disables xPosting via WordPress Cron hook.</em></p>
		<p><?php echo go_xpost()->cron()->register_cron_link(); ?></p>
		<?php
	} // END if
	?>
	<hr />
	<h3>Batch xPosting</h3>
	<p><em>This is an advanced feature and should <strong>only</strong> be used with full understanding of the code.</em></p>
	<form method="get" action="admin-ajax.php" class="<?php echo esc_attr( $this->slug . '-batch-form' ); ?>">
		<p>
			<label for="batch_name"><strong>Batch name</strong></label><br />
			<input type="text" name="batch_name" />
		</p>
		<p>
			<label for="post_types"><strong>Post types</strong></label><br />
			<input type="text" name="post_types" /><br />
			<em>Comma separated list.</em>
		</p>
		<p>
			<label for="num"><strong>Number of posts per batch</strong></label><br />
			<input type="number" name="num" placeholder="10" />
		</p>
		<input type="submit" class="button button-primary" value="Process" />
		<input type="hidden" name="action" value="go_xpost_batch" />
	</form>
</div>