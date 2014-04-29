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
		$valid_post_types = array(
			//'post',
			'go-report',
		);

		if ( ! in_array( get_post( $post_id )->post_type, $valid_post_types ) )
		{
			return FALSE;
		} // END if

		//we're ignoring timezone, since the difference is larege enough it shouldn't matter.
		$interval = time() - strtotime( get_post( $post_id )->post_date );
		$meta = get_post_meta( $post_id, 'go-research-options', TRUE );

		//no quarterly wrap-ups older than 120 days
		if ( 'quarterly-wrap-up' == $meta['content-type'] && (  10368000 < $interval ) )
		{
			return FALSE;
		}//END if

		//nothing older than 18 months
		if ( 47433514 < $interval )
		{
			return FALSE;
		}//END if

		return TRUE;
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
		// replace the content with the excerpt
		if ( ! empty( $xpost->post->post_excerpt ) )
		{
			$xpost->post->post_content = $xpost->post->post_excerpt;
		}


		if ( 'go-report' == $xpost->post->post_type )
		{
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

			$toc = '<p>Table of Contents</p>' . go_reports()->table_of_contents( FALSE, $post_id );

			$xpost->post->post_content .= $toc;
		}//END if

		// go-report doesn't exist on GO
		$xpost->post->post_type = 'post';

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

		// set guest author data
		$xpost->meta['guest_author'] = get_the_author_meta( 'display_name', $xpost->post->post_author );

		$xpost->meta['go_guest']     = array(
			'author_name'      => $xpost->meta['guest_author'],
			'author_override'  => TRUE,
			'author_url'       => get_author_posts_url( $xpost->post->post_author ),
			'post_id'          => 0, // go-guest saves this value but doesn't actually use it; we don't know it yet in any case
			'publication_name' => '',
			'publication_url'  => '',
			'source_override'  => FALSE,
			'source_url'       => '',
		);

		// reports get an extra taxonomy term
		$xpost->terms['go_syn_media'][] = 'report';
		$xpost->terms['channel'][] = 'pro';
		$xpost->terms['primary_channel'][] = 'pro';

		// merge all the terms into the post_tags
		$xpost->terms['post_tag'] = array_merge(
			(array) $xpost->terms['company'],
			(array) $xpost->terms['person'],
			(array) $xpost->terms['post_tag'],
			(array) $xpost->terms['technology']
		);

		// unset the unused taxonomies
		unset( $xpost->terms['author'] );
		unset( $xpost->terms['category'] );

		return $xpost;
	}// END post_filter
}// END GO_XPost_Filter_Reports
