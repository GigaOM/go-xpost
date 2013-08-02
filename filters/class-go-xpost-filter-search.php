<?php

/*
Filter Name: Posts Appropriate For search.gigaom.com -> Endpoint
*/

class GO_XPost_Filter_Search extends GO_XPost_Filter
{
	/**
	 * Determine whether a post_id should ping a site
	 *
	 * @param  absint $post_id
	 * @return boolean
	 */
	public function should_send_post( $post_id )
	{
		if ( apply_filters( 'go_sponsor_posts_is_sponsor_post', FALSE, $post_id ) )
		{
			return FALSE;
		} // END if

		$valid_post_types = array(
			'go_shortpost',
			'go-report',
			'go-report-section',
			'go-datamodule',
			'go_webinar',
			'post',
		);

		if ( ! in_array( get_post( $post_id )->post_type, $valid_post_types ) )
		{
			return FALSE;
		} // END if
		
		$invalid_categories = array(
			'links',          // We don't want currated links from pro going into search
			'poll-summaries', // Same for poll summaries
		);
		
		$categories = wp_get_object_terms( $post_id, array( 'category' ), array( 'fields' => 'slugs' ) );

		foreach ( $categories as $category )
		{
			if ( in_array( $category, $invalid_categories ) )
			{
				return FALSE;
			} // END if
		} // END foreach

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
		global $post;

		// Make sure we've got $post global set to our xpost so anything that relies on it can use it
		$post = $xpost->post;
		$post->ID = $post_id;
		
		// Will set this later to Subscription if appropriate
		$availability = 'Free';
		
		// go-property will come from the current property set in go_config
		$xpost->terms['go-property'][] = go_config()->get_property();

		// vertical can potentially come from vertical, channel, primary_channel, and category -> child of Topics
		if ( isset( $xpost->terms['channel'] ) )
		{
			foreach ( $xpost->terms['channel'] as $channel )
			{
				$xpost->terms['vertical'][] = ucwords( $channel );
			} // END foreach
		} // END if

		if ( isset( $xpost->terms['primary_channel'] ) )
		{
			foreach ( $xpost->terms['primary_channel'] as $primary_channel )
			{
				$xpost->terms['vertical'][] = ucwords( $primary_channel );
			} // END foreach
		} // END if

		if ( isset( $xpost->terms['vertical'] ) )
		{
			$xpost->terms['vertical'] = array_unique( $xpost->terms['vertical'] );
		} // END if
		
		// This can be deleted once category to vertical stuff has launched
		if ( isset( $xpost->terms['category'] ) )
		{
			$topics_term     = get_term_by( 'name', 'Topics', 'category' );
			$topics_children = get_terms( array( 'category' ), array( 'child_of' => $topics_term->term_id ) );
			$topics_children = $this->parse_terms_array( $topics_children );

			foreach ( $xpost->terms['category'] as $category )
			{
				if ( in_array( $category, $topics_children ) )
				{
					$xpost->terms['vertical'][] = $category;
				} // END if
			} // END foreach
		} // END if

		// go-type is the fun one, it will come a variety of sources
		$xpost->terms['go-type'] = array();

		// This will determine the go-type value for Pro posts
		if ( isset( $xpost->terms['category'] ) )
		{
			foreach ( $xpost->terms['category'] as $category )
			{
				if ( 
					'Briefings' == $category
					|| 'Research Briefings' == $category
					|| 'Research Notes' == $category
					|| 'Long Views' == $category
				)
				{
					$xpost->terms['go-type'][] = 'Report';
					$availability = 'Subscription';
				}
				elseif ( 'Sector Roadmaps' == $category )
				{
					$xpost->terms['go-type'][] = 'Sector Roadmap';
					$availability = 'Subscription';
				} // END elseif
				elseif ( 'Quarterly Wrap Ups' == $category )
				{
					$xpost->terms['go-type'][] = 'Quarterly Wrap Up';
					$availability = 'Subscription';
				} // END elseif
			} // END foreach
			
			// If none of the above were triggered we've got a Blog Post
			if ( empty( $xpost->terms['go-type'] ) )
			{
				$xpost->terms['go-type'] = 'Blog Post';
			} // END if
		} // END if
		
		if ( 'go-datamodule' == $xpost->post->post_type )
		{
			$xpost->terms['go-type'][] = 'Chart';
		} // END if
		elseif ( 0 == strncmp( 'go-report', $xpost->post->post_type, 9 ) )
		{	
			// If this is a section we need to set it's status based on the parent
			if ( 'inherit' == $xpost->post->post_status )
			{
				$parent_report = go_reports()->get_current_report();
				$xpost->post->post_status = $parent_report->post_status;
			} // END if
			elseif ( 'go-report' == $xpost->post->post_type )
			{
				// If this is a report parent post we need to make sure the children get updated too
				$report_children = go_reports()->get_report_children();

				foreach ( $report_children as $report_child )
				{					
					go_xpost()->process_post( $report_child->ID );
				} // END foreach
			} // END elseif
			
			// At some point it may be necessary to tweak how these next 6 lines work if the report plugin ever get's used elsewhere
			if ( ! in_array( 'Report', $xpost->terms['go-type'] ) )
			{
				$xpost->terms['go-type'][] = 'Report';
			} // END if
			
			$availability = 'Subscription';
		} // END elseif
		elseif ( 'go_shortpost' == $xpost->post->post_type )
		{
			$xpost->terms['go-type'][] = 'Blog Post';
		}// end elseif
		elseif ( function_exists( 'go_waterfall_options' ) ) // or maybe go_config()->dir != '_pro' at some point?
		{
			if (
				'video' == go_waterfall_options()->get_type( $post_id )
				|| ( isset( $xpost->terms['go_syn_media'] ) && in_array( 'video', $xpost->terms['go_syn_media'] ) )
			)
			{
				$xpost->terms['go-type'][] = 'Video';
			} // END elseif
			elseif ( 
				'audio' == go_waterfall_options()->get_type( $post_id )
				|| ( isset( $xpost->terms['go_syn_media'] ) && in_array( 'podcast', $xpost->terms['go_syn_media'] ) )
			)
			{
				$xpost->terms['go-type'][] = 'Audio';
			} // END elseif
			elseif (
				'gigaom' == go_waterfall_options()->get_type( $post_id )
				|| 'paidcontent' == go_waterfall_options()->get_type( $post_id )
			)
			{
				$xpost->terms['go-type'][] = 'Blog Post';
			} // END elseif

			// multi-page posts with 3 OR MORE pages on GO/pC are also reports
			if ( preg_match_all( '/--nextpage--/', $xpost->post->post_content, $matches ) )
			{
				if ( 2 <= count( $matches ) )
				{
					$xpost->terms['go-type'][] = 'Report';
				} // END if
			} // END if
		} // END elseif

		// Default go-type value in case it doesn't get set by something above? Maybe?
		if ( ! count( $xpost->terms['go-type'] ) )
		{
			$xpost->terms['go-type'][] = 'Blog Post';
		} // END else

		// search does not need the thumbnails
		foreach( $xpost->meta as $meta_key => $meta_values )
		{
			if ( strpos( $meta_key, '_thumbnail_id' ) !== FALSE )
			{
				unset( $xpost->meta[ $meta_key ] );
			}// end if
		}// end foreach

		// Set content availability
		$xpost->terms['go-type'][] = $availability;

		// Remove any post_format if it exists
		unset( $xpost->terms['post_format'] );

		// Force all post types to be a post for search
		$xpost->post->post_type = 'post';
		
		// Unset the ID value since setting it for the $post global resets it in the $xpost->post object
		unset( $xpost->post->ID );

		return $xpost;
	} // END post_filter

	/**
	 * Return a simplified array of terms
	 */
	public function parse_terms_array( $terms )
	{
		$parsed_terms = array();

		if ( is_array( $terms ) )
		{
			foreach ( $terms as $term )
			{
				$parsed_terms[] = $term->name;
			} // END foreach
		} // END if

		return $parsed_terms;
	} // END parse_terms_array
} // END GO_XPost_Filter_Search
