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
		add_action( 'go_xpost_process_cron', array( $this, 'process_cron' ) );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
	}

	public function init()
	{
		// Schedule the cron job if it's not already scheduled
		if ( ! wp_next_scheduled( 'go_xpost_process_cron' ) )
		{
			wp_schedule_event( time(), $this->slug . '-interval', 'go_xpost_process_cron' );
		}

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
} // END GO_XPost_Cron

function go_xpost_cron()
{
	global $go_xpost_cron;

	if ( ! isset( $go_xpost_cron ) )
	{
		$go_xpost_cron = new GO_XPost_Cron();
	}// end if

	return $go_xpost_cron;
}// end go_xpost_cron
