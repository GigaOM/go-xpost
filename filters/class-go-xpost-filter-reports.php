<?php

/*
Filter Name: Reports (Parent) -> Endpoint
*/

class GO_XPost_Filter_Reports extends GO_XPost_Filter
{
	/**
	 * Determine whether a post_id should ping a property
	 *
	 * @param  absint $post_id
	 * @return boolean
	 */
	public function should_send_post( $post_id )
	{
		// to actually get reports, we should check (in pseudocode):
		// post_type=go-report || ( post_type=post && array_intersect( $post_category_slugs , $report_category_slugs )
		$valid_post_types = array(
			// 'go_shortpost', // analyst blog posts DISABLED for now, as that would be a change from previous behavior
			'go-report',
		);

		if ( in_array( get_post( $post_id )->post_type, $valid_post_types ) )
		{
			return TRUE;
		} // END if

		return FALSE;
	} // END should_send_post
	
	/**
	 * Alter the $xpost object before returning it to the endpoint
	 * Note: $xpost is NOT a WP_Post object, but it contains one in $xpost->post
	 *
	 * @param  object $xpost
	 * @param  int $post_id
	 * @return custom object containing WP_Post
	 */
	public function post_filter( $xpost, $post_id )
	{
		// go_shortpost and go-report don't exist on GO or pC
		$xpost->post->post_type = 'post';

		// replace the content with the excerpt
		if ( ! empty( $xpost->post->post_excerpt ))
		{
			$xpost->post->post_content = $xpost->post->post_excerpt;
		}

		// replace the excerpt with the shorter gomcom excerpt
		if ( ! empty( $xpost->meta['gomcom_ingestion_excerpt'] ) )
		{
			// this is the older postmeta prior to the creation of the new report post type
			$xpost->post->post_excerpt = $xpost->meta['gomcom_ingestion_excerpt'];
		}

		if ( $teaser = go_reports()->get_post_custom( $post_id, 'teaser' ) )
		{
			$xpost->post->post_excerpt = $teaser;
		} // END if

		// replace the title with the gomcom title
		if ( ! empty( $xpost->meta['gomcom_ingestion_headline'] ) )
		{
			// this is the older postmeta prior to the creation of the new report post type
			$xpost->post->post_title = $xpost->meta['gomcom_ingestion_headline'];
		}
				
		if ( $marketing_title = go_reports()->get_post_custom( $post_id, 'marketing-title' ) )
		{
			$xpost->post->post_title = $marketing_title;
		} // END if
		

		// unset unused meta
		unset( $xpost->meta['document_full_id'] );
		unset( $xpost->meta['_rc_cwp_write_panel_id'] );
		unset( $xpost->meta['feature'] );
		unset( $xpost->meta['coming-soon'] );
		unset( $xpost->meta['popular'] );
		unset( $xpost->meta['company-listing'] );
		unset( $xpost->meta['scribd-sample-id'] );
		unset( $xpost->meta['scribd-full-id'] );
		unset( $xpost->meta['teaser'] );
		unset( $xpost->meta['tableofcontents'] );
		unset( $xpost->meta['_encloseme'] );
		unset( $xpost->meta['gomcom_ingestion_headline'] );
		unset( $xpost->meta['gomcom_ingestion_excerpt'] );

		// reports get an extra taxonomy term
		$xpost->terms['go_syn_media'][] = 'report';
		$xpost->terms['channel'][] = 'pro';

		// merge all the terms into the post_tags
		$xpost->terms['post_tag'] = array_merge(
			(array) $xpost->terms['post_tag'],
			(array) $xpost->terms['company'],
			(array) $xpost->terms['technology']
		);

		// unset the unused taxonomies
		unset( $xpost->terms['category'] );
		unset( $xpost->terms['author'] );

		return $xpost;
	} // END post_filter
} // END GO_XPost_Filter_Reports