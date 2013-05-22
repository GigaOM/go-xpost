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
			'links',
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
		// go-property will come from the current property set in go_config
		$xpost->terms['go-property'][] = go_config()->get_property();

		// vertical can potentially come from vertical, channel, primary_channel, and category -> child of Topics
		if ( isset( $xpost->terms['channel'] ) )
		{
			foreach ( $xpost->terms['channel'] as $channel )
			{
				$xpost->terms['vertical'][] = $channel;
			} // END foreach
		} // END if

		if ( isset( $xpost->terms['primary_channel'] ) )
		{
			foreach ( $xpost->terms['primary_channel'] as $primary_channel )
			{
				$xpost->terms['vertical'][] = $primary_channel;
			} // END foreach
		} // END if
		
		// This will need to be refactored to only worry about if something is in the Briefings category once we switch topics to verticals
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
				elseif ( 'Briefings' == $category )
				{
					$is_report = TRUE;
				}
			} // END foreach
		} // END if

		if ( isset( $xpost->terms['vertical'] ) )
		{
			$xpost->terms['vertical'] = array_unique( $xpost->terms['vertical'] );
		} // END if

		// go-type is the fun one, it will come a variety of sources
		$xpost->terms['go-type'] = array();
		if ( 'go-datamodule' == $xpost->post->post_type )
		{
			$xpost->terms['go-type'][] = 'Chart';
			$xpost->post->post_type = 'post';
		} // END if
		elseif (
			0 == strncmp( 'go-report', $xpost->post->post_type, 9 )
			|| isset( $is_report )
		)
		{
			$xpost->terms['go-type'][] = 'Report';
			$xpost->post->post_type = 'post';
		} // END elseif
		elseif ( 'go_shortpost' == $xpost->post->post_type )
		{
			$xpost->terms['go-type'][] = 'News';
			$xpost->post->post_type = 'post';
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
			elseif ( 'podcast' == go_waterfall_options()->get_type( $post_id ) )
			{
				$xpost->terms['go-type'][] = 'Podcast';
			} // END elseif
			elseif (
				'gigaom' == go_waterfall_options()->get_type( $post_id )
				|| 'paidcontent' == go_waterfall_options()->get_type( $post_id )
			)
			{
				$xpost->terms['go-type'][] = 'News';
			} // END elseif

			// multi-page posts on GO/pC are also reports
			if ( preg_match( '/--nextpage--/', $xpost->post->post_content ) )
			{
				$xpost->terms['go-type'][] = 'Report';
			} // END if
		} // END elseif

		// Default go-type value in case it doesn't get set by something above? Maybe?
		if ( ! count( $xpost->terms['go-type'] ) )
		{
			$xpost->terms['go-type'][] = 'News';
		} // END else

		// search does not need the thumbnails
		foreach( $xpost->meta as $meta_key => $meta_values )
		{
			if ( strpos( $meta_key, '_thumbnail_id' ) !== FALSE )
			{
				unset( $xpost->meta[ $meta_key ] );
			}// end if
		}// end foreach

		// coerce everything to be a post, because it's easier
		$xpost->post->post_type = 'post';

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
