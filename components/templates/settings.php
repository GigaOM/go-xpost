<!-- This is for the Add button so it has a template to work off of. -->
<li style="display: none;" class="<?php echo $this->slug; ?>-setting-template">
	<a href="#remove-endpoint" title="Remove Filter/Endpoint" class="<?php echo $this->slug; ?>-delete-endpoint">Delete</a>
	<div class="<?php echo $this->slug; ?>-filter">
		<label for="<?php echo $this->slug; ?>-filter-keynum"><strong>Filter</strong></label><br />
		<select name='<?php echo $this->slug; ?>-filter-keynum' class='select' id="<?php echo $this->slug; ?>-filter-keynum">
			<?php echo $this->_build_options( $filters, '' ); ?>
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
						<input class="input" type="text" name="<?php echo $this->slug; ?>-endpoint-<?php echo $key + 1; ?>" id="<?php echo $this->slug; ?>-endpoint-<?php echo $key + 1; ?>" value="<?php echo esc_attr( $item['endpoint'] ); ?>" placeholder="http://domain/wp-admin/admin-ajax.php" />
					</div>
					<input type="hidden" name="<?php echo $this->slug; ?>-number-<?php echo $key + 1; ?>" value="<?php echo $key + 1; ?>" class="number" />
				</li>
				<?php
			} // end foreach
			?>
		</ul>

		<div class="<?php echo $this->slug; ?>-secret">
			<label for="<?php echo $this->slug; ?>-secret"><strong>Shared secret</strong></label><br />
			<input class="input" type="text" name="<?php echo $this->slug; ?>-secret" id="<?php echo $this->slug; ?>-secret" value="<?php echo esc_attr( $secret ); ?>" placeholder="Something complex..." /><br />
			<em>Secret that is shared between all of the sites being xPosted to/from.</em>
		</div>

		<div class="<?php echo $this->slug; ?>-method">
			<label for="<?php echo $this->slug; ?>-method"><strong>Request method</strong></label><br />
			<input type="radio" name="<?php echo $this->slug; ?>-method" id="<?php echo $this->slug; ?>-method-get" value="GET" <?php if ( 'GET' == $method || ! $method ) { echo 'checked'; } ?>/> GET<br />
			<input type="radio" name="<?php echo $this->slug; ?>-method" id="<?php echo $this->slug; ?>-method-get" value="POST" <?php if ( 'POST' == $method ) { echo 'checked'; } ?>/> POST<br />
		</div>

		<p class="submit">
			<?php wp_nonce_field( 'save-' . $this->slug . '-settings' ); ?>
			<input type="hidden" name="<?php echo $this->slug; ?>-setting-numbers" class="<?php echo $this->slug; ?>-setting-numbers" value="<?php echo substr( $setting_numbers, 0, -1 ); ?>" />
			<input type="submit" class="button button-primary" name="save-<?php echo $this->slug; ?>-settings" value="Save Changes" />
		</p>
	</form>
	<hr />
	<h3>Batch xPosting</h3>
	<em>This is an advanced feature and should <strong>only</strong> be used with full understanding of the code.</em>
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