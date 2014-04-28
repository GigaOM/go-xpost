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
			'go-report',
			'post',
		);

		if ( ! in_array( get_post( $post_id )->post_type, $valid_post_types ) )
		{
			return FALSE;
		} // END if

		$today18mos = new DateTime();
		$post_date = new DateTime( get_post( $post_id )->post_date );

		//nothing older than 18 months
		if ( $post_date < $today18mos->sub( new DateInterval( 'P18M' ) ) )
		{
			return FALSE;
		}//END if

		$meta = get_post_meta( $post_id, 'go-research-options', TRUE );
		$today120days = new DateTime();

		//no quarterly wrap-ups older than 120 days
		if ( 'quarterly-wrap-up' == $meta['content-type'] && $post_date < $today120days->sub( new DateInterval( 'P120D' ) ) )
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

			$child_meta = $xpost->meta['go-report-children'];

			$toc = '<p>Table of Contents</p><ol>';

			foreach ( $child_meta as $child )
			{
				$toc .= '<li>';
				$toc .= '<a href="' . get_permalink( $child->ID ) . '">' . $child->post_title . '</a>';
				$toc .= '</li>';
			}//END foreach

			$toc .= '</ol>';

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
			'post_id'          => 0, // go-guest saves this value but doesn't actually use it; we don't know it yet in any case
			'author_override'  => TRUE,
			'source_override'  => FALSE,
			'author_name'      => $xpost->meta['guest_author'],
			'author_url'       => get_permalink(  go_analyst()->get_post_by_user_id( $xpost->post->post_author )->ID ),
			'source_url'       => '',
			'publication_name' => '',
			'publication_url'  => '',
		);

		// reports get an extra taxonomy term
		$xpost->terms['go_syn_media'][] = 'report';
		$xpost->terms['channel'][] = 'pro';
		$xpost->terms['primary_channel'][] = 'pro';

		// merge all the terms into the post_tags
		$xpost->terms['post_tag'] = array_merge(
			(array) $xpost->terms['company'],
			(array) $xpost->terms['post_tag'],
			(array) $xpost->terms['person'],
			(array) $xpost->terms['technology']
		);

		// unset the unused taxonomies
		unset( $xpost->terms['author'] );
		unset( $xpost->terms['category'] );

		return $xpost;
	}// END post_filter
}// END GO_XPost_Filter_Reports
