<?php

/**
 * Provides base functionality for cross posting,
 * could be leveraged via command line in addition to the typical implementation of GO_XPost
 */

class GO_XPost_Cron
{
	public $slug = 'go-xpost-cron';

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_ajax_go_xpost_register_cron', array( $this, 'register_cron' ) );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
	}

	public function init()
	{
		// Taxonomy for keeping track of posts that have already been xposted via the cron job
		register_taxonomy(
			$this->slug,
			go_xpost()->config->cron_post_types,
			array(
				'label'   => 'Gigaom xPost Cron',
				'public'  => FALSE,
				'rewrite' => FALSE,
			)
		);
	} // END init

	public function process_cron()
	{
		$posts = $this->get_posts(
			go_xpost()->config->cron_post_types,
			go_xpost()->config->cron_term,
			go_xpost()->config->cron_limit
		);

		if ( ! $posts )
		{
			return;
		} // END if

		foreach ( $posts as $post )
		{
			if ( ! isset( $post->ID ) )
			{
				continue;
			} // END if

			go_xpost()->process_post( $post->ID );
			wp_set_post_terms( $post->ID, 'posted', $this->slug );

			sleep( 2 );
		} // END foreach
	} // END process_cron

	public function cron_schedules( $schedules )
	{
		$schedules[ $this->slug . '-interval' ] = array(
			'interval' => absint( go_xpost()->config()->cron_interval ) * 60,
			'display'  => 'Cron interval for Gigaom xPost (' . absint( go_xpost()->config()->cron_interval ) . 'min)',
		);

		return $schedules;
	} // END cron_schedules

	public function get_posts( $post_types, $term, $limit = 10 )
	{
		$args = array(
			'post_status' => array( 'any' ),
			'post_type' => (array) $post_types,
			'tax_query' => array(
				array(
					'taxonomy' => $this->slug,
					'field'    => 'slug',
					'terms'    => array( sanitize_key( $term ) ),
					'operator' => 'NOT IN',
				),
			),
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'posts_per_page' => $limit,
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() )
		{
			return FALSE;
		} // END if

		return $query->posts;
	} // END get_posts

	public function register_cron_link()
	{
		$args = array(
			'action' => 'go_xpost_register_cron',
			'nonce'  => wp_create_nonce( $this->slug . '-registier-cron' ),
		);

		if ( ! wp_next_scheduled( 'go_xpost_process_cron' ) )
		{
			$direction = 'Enable';
		} // END if
		else
		{
			$direction = 'Disable';
		} // END else

		$url = add_query_arg( $args, admin_url( 'admin-ajax.php' ) );

		return '<a href="' . esc_url( $url ) . '" title="' . esc_attr( $direction ) . ' Cron xPosting" class="button-primary">' . esc_html( $direction ) . ' Cron xPosting</a>';
	} // END register_cron_link

	public function register_cron()
	{
		if ( ! current_user_can( 'manage_options' ) )
		{
			wp_die( 'You don not have permission to be here!', 'Bad user! Bad!' );
		} // END if

		if ( ! wp_verify_nonce( $_GET['nonce'], $this->slug . '-registier-cron' ) )
		{
			wp_nonce_ays( 'register-cron' );
		} // END if

		// Schedule the cron job if it's not already scheduled
		if ( ! $timestamp = wp_next_scheduled( 'go_xpost_process_cron' ) )
		{
			wp_schedule_event( time(), $this->slug . '-interval', 'go_xpost_process_cron' );
			$success = 'registered';
		}
		else
		{
			wp_unschedule_event( $timestamp, 'go_xpost_process_cron' );
			$success = 'unregistered';
		} // END else

		wp_safe_redirect( add_query_arg( 'success', $success, admin_url( 'options-general.php?page=go-xpost-settings' ) ) );
	} // END register_cron
} // END GO_XPost_Cron
