<?php

class GO_XPost_Filter_Pro
{
	// URL of the site that this filter will apply to
	public $endpoint_url;

	public function __construct( $endpoint_url )
	{
		$this->endpoint_url = $endpoint_url;
		add_filter( 'go_xpost_post_filter', array( $this, 'post_filter' ) );
	} // END __construct

	/**
	 * Determine whether a post_id should ping a property
	 *
	 * @param  absint $post_id, string $target_property
	 * @return boolean
	 */
	public function should_process_post( $post_id, $target_property )
	{
		// GO should not get graphs from PRO
		if ( 'gigaom' == $target_property && 'go-datamodule' == get_post( $post_id)->post_type )
		{
			return FALSE;
		} // END if
		
		return TRUE;
	} // END should_process_post

	/**
	 * Get the $post object before returning it to a property
	 *
	 * @param  object $post, string $requesting_property
	 * @return $post WP_Post
	 */
	public function post_filter( $post, $requesting_property )
	{
		if ( 'gigaom' == $requesting_property && 'go-datamodule' != $post->post_type )
		{
			// go_shortpost and go-report don't exist on GO or pC
			$post->post->post_type = 'post';
			
			// replace the content with the excerpt
			if ( ! empty( $post->post->post_excerpt ))
			{
				$post->post->post_content = $post->post->post_excerpt;
			}

			// replace the excerpt with the shorter gomcom excerpt
			if ( ! empty( $post->meta['gomcom_ingestion_excerpt'] ))
			{
				$post->post->post_excerpt = $post->meta['gomcom_ingestion_excerpt'];
			}

			// replace the title with the gomcom title
			if ( ! empty( $post->meta['gomcom_ingestion_headline'] ))
			{
				$post->post->post_title = $post->meta['gomcom_ingestion_headline'];
			}

			// unset unused meta
			unset( $post->meta['document_full_id'] );
			unset( $post->meta['_rc_cwp_write_panel_id'] );
			unset( $post->meta['feature'] );
			unset( $post->meta['coming-soon'] );
			unset( $post->meta['popular'] );
			unset( $post->meta['company-listing'] );
			unset( $post->meta['scribd-sample-id'] );
			unset( $post->meta['scribd-full-id'] );
			unset( $post->meta['teaser'] );
			unset( $post->meta['tableofcontents'] );
			unset( $post->meta['_encloseme'] );
			unset( $post->meta['gomcom_ingestion_headline'] );
			unset( $post->meta['gomcom_ingestion_excerpt'] );

			// reports get an extra taxonomy term
			$post->terms['go_syn_media'][] = 'report';
			$post->terms['channel'][] = 'pro';

			// merge all the terms into the post_tags
			$post->terms['post_tag'] = array_merge(
				(array) $post->terms['post_tag'],
				(array) $post->terms['company'],
				(array) $post->terms['technology']
			);

			// unset the unused taxonomies
			unset( $post->terms['category'] );
			unset( $post->terms['author'] );
		} // END if
		
		return $post;
	} // END post_filter
} // END GO_XPost_Pro