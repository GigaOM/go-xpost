<?php

class GO_XPost_Pro extends GO_XPost
{
	public function __construct()
	{
		add_filter( 'go_xpost_process_post_' . $this->property, array( $this, 'go_xpost_get_post' ), 2 );
		add_filter( 'go_xpost_get_post_' . $this->property, array( $this, 'go_xpost_get_post' ), 2 );
	}// end __construct

	/**
	 * Filter whether a post_id should ping a property
	 * 
	 * @param  absint $post_id, string $target_property
	 * @return $post_id or FALSE
	 */
	public function go_xpost_process_post_pro( $post_id, $target_property )
	{
		return $post_id;
	} // END go_xpost_process_post_pro
	
	/**
	 * Filter the $post object before returning it to a property
	 * 
	 * @param  object $post, string $requesting_property
	 * @return $post
	 */
	public function go_xpost_get_post_pro( $post, $requesting_property )
	{
		// this part doesn't do anything yet, as only posts get through an earlier filter
		if( in_array( $post->post->post_type, array( 'go_shortpost', 'go_report' )))
		{
			$post->post->post_type = 'post';
		}

		// replace the content with the excerpt
		if( ! empty( $post->post->post_excerpt ))
		{
			$post->post->post_content = $post->post->post_excerpt;
		}

		// replace the excerpt with the shorter gomcom excerpt
		if( ! empty( $post->meta['gomcom_ingestion_excerpt'] ))
		{
			$post->post->post_excerpt = $post->meta['gomcom_ingestion_excerpt'];
		}

		// replace the title with the gomcom title
		if( ! empty( $post->meta['gomcom_ingestion_headline'] ))
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

		return $post;
	} // END go_xpost_get_post_pro
} // END GO_XPost_Pro