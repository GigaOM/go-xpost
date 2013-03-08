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

		return TRUE;
	} // END should_send_post
	
	/**
	 * Alter the $post object before returning it to the endpoint
	 *
	 * @param  object $post
	 * @param  int $post_id
	 * @return $post WP_Post
	 */
	public function post_filter( $post, $post_id )
	{
		// go-property will come from the current property set in go_config
		$post->terms['go-property'][] = go_config()->get_property();
		
		// go-vertical can potentially come from channel, primary_channel, and category -> child of Topics
		if ( isset( $post->terms['channel'] ) )
		{
			foreach ( $post->terms['channel'] as $channel )
			{
				$post->terms['go-vertical'][] = $channel;
			} // END foreach
		} // END if
		
		if ( isset( $post->terms['primary_channel'] ) )
		{
			foreach ( $post->terms['primary_channel'] as $primary_channel )
			{
				$post->terms['go-vertical'][] = $primary_channel;
			} // END foreach
		} // END if
		
		if ( isset( $post->terms['category'] ) )
		{
			$topics_term     = get_term_by( 'name', 'Topics', 'category' );
			$topics_children = get_terms( array( 'category' ), array( 'child_of' => $topics_term->term_id ) );
			$topics_children = $this->parse_terms_array( $topics_children );	
			
			foreach ( $post->terms['category'] as $category )
			{
				if ( in_array( $category, $topics_children ) )
				{
					$post->terms['go-vertical'][] = $category;
				} // END if
				elseif ( 'Briefings' == $category )
				{
					$is_report = TRUE;
				}
			} // END foreach
		} // END if
		
		if ( isset( $post->terms['go-vertical'] ) )
		{
			$post->terms['go-vertical'] = array_unique( $post->terms['go-vertical'] );
		} // END if
		
		// go-type is the fun one, it will come a variety of sources
		if ( 'go-datamodule' == $post->post_type )
		{
			$post->terms['go-type'][] = 'Chart';
		} // END if
		elseif ( 
			0 == strncmp( 'go-report', $post->post_type, 9 ) 
			|| isset( $is_report )
		)
		{
			$post->terms['go-type'][] = 'Report';
		} // END elseif
		elseif ( function_exists( 'go_waterfall_options' ) ) // or maybe go_config()->dir != '_pro' at some point?
		{
			if ( 
				'video' == go_waterfall_options()->get_type( $post_id )
				|| ( isset( $post->terms['go_syn_media'] ) && in_array( 'video', $post->terms['go_syn_media'] ) )
			)
			{
				$post->terms['go-type'][] = 'Video';
			} // END elseif
			elseif ( 'podcast' == go_waterfall_options()->get_type( $post_id ) )
			{
				$post->terms['go-type'][] = 'Podcast';
			} // END elseif
			elseif ( 
				'gigaom' == go_waterfall_options()->get_type( $post_id )
				|| 'paidcontent' == go_waterfall_options()->get_type( $post_id )	
			)
			{
				$post->terms['go-type'][] = 'News';
			} // END elseif
		} // END elseif
		
		// Default go-type value in case it doesn't get set by something above? Maybe?
		if ( ! isset( $post->terms['go-type'] ) ) 
		{
			$post->terms['go-type'][] = 'Content';
		} // END else
		
		return $post;
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