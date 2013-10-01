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
		$post = get_post( $post_id );

		// exclude sponsor posts (we only filter this on GO/pC / does not apply to underwritten reports)
		if ( apply_filters( 'go_sponsor_posts_is_sponsor_post', FALSE, $post_id ) )
		{
			return FALSE;
		} // END if

		// check for valid post types
		$valid_post_types = array(
			'go_shortpost',
			'go-report',
			//'go-report-section', // temporarily disabled, need to come up with a better plan
			//'go-datamodule',     // temporarily removed, per https://github.com/GigaOM/legacy-pro/issues/1098#issuecomment-23899882
			'go_webinar',
			'post',
		);

		if ( ! in_array( $post->post_type, $valid_post_types ) )
		{
			return FALSE;
		} // END if

		// only include charts from the research property
		if (
			'go-datamodule' == $post->post_type &&
			'research' != go_config()->get_property_slug()
		)
		{
			return FALSE;
		} // END if

		// exclude report subsections that are about the author or about gigaom research
		if (
			'go-report-section' == $post->post_type &&
			'research' == go_config()->get_property_slug()
		)
		{
			if (
				0 === stripos( $post->post_title, 'about gigaom' ) || // match "About GigaOM..."
				(
					0 === stripos( $post->post_title, 'about ' ) && // match "About Keren Elazari"
					5 < $post->menu_order // exclude early sections, which might begin with "about" for other reasons
				)
			)
			{
				return FALSE;
			}
		} // END if

		// exclude some categories from Pro
		if ( 'research' != go_config()->get_property_slug() )
		{
			$invalid_categories = array(
				'links',          // We don't want currated links from pro going into search
				'poll-summaries', // Same for poll summaries
			);

			$categories = get_the_terms( $post_id, array( 'category' ), array( 'fields' => 'slugs' ) );

			foreach ( $categories as $category )
			{
				if ( in_array( $category, $invalid_categories ) )
				{
					return FALSE;
				} // END if
			} // END foreach
		}

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
		// for easier QA
		$xpost->should_send_post = $this->should_send_post( $post_id );

		// this is dangerous, note how we have to unset the post ID at the end
		global $post;

		// Make sure we've got $post global set to our xpost so anything that relies on it can use it
		$post = $xpost->post;
		$post->ID = $post_id;

		// shorten the post excerpt if we have one (preserve the full excerpt in the post content, so we can still search it)
		if ( ! empty( $xpost->post->post_excerpt ) )
		{
			$xpost->post->post_content = $xpost->post->post_excerpt . "\n\n" . $xpost->post->post_content;
			$xpost->post->post_excerpt = trim( wp_trim_words( $xpost->post->post_content, 31, '' ), '.' ) . '&hellip;';
		}
		// or generate a post excerpt if we don't have one (saves us from having to generate it when displaying the posts)
		else
		{
			$xpost->post->post_excerpt = trim( wp_trim_words( $xpost->post->post_content, 31, '' ), '.' ) . '&hellip;';
		}

		// Will change this to Subscription later if appropriate
		$availability = 'Free';

		// go-property will come from the current property set in go_config
		$xpost->terms['go-property'][] = go_config()->get_property();

		// go-type is the fun one, it will come a variety of sources AND is used differently in Research
		$xpost->terms['go-type'] = array();

		if ( 'research' == go_config()->get_property_slug() )
		{
			if ( 'go_webinar' == $xpost->post->post_type )
			{
				// set the type
				$xpost->terms['go-type'][] = 'Webinar';

				// set future scheduled webinars to be published so they can appear in search
				if ( 'future' == $xpost->post->post_status )
				{
					$xpost->post->post_status = 'publish';
				}

				// remove the go_webinar meta, as it's unused on Search.GO
				unset( $xpost->meta['go_webinar'] );
			} // END if
			elseif ( 0 == strncmp( 'go-report', $xpost->post->post_type, 9 ) )
			{
				// set the availability up top
				// we may need to recnosider this if we use the report plugin on Gigaom or other non-subscriber sites
				$availability = 'Subscription';

				// TODO: Remove this when we launch Research
				// Sector Roadmaps and Quarterly Wrap Ups are special
				if ( isset( $xpost->terms['category'] ) )
				{
					foreach ( $xpost->terms['category'] as $category )
					{
						switch ( strtolower( $category ) )
						{
							case 'sector roadmaps':
								$xpost->terms['go-type'][] = 'Sector Roadmap';
								break;
							case 'quarterly wrap ups':
							case 'quarterly wrap-ups':
								$xpost->terms['go-type'][] = 'Quarterly Wrap-Up';
								break;

						} // END switch
					} // END foreach
				} // END if

				// Set go-type value for go-report and go-report-section types
				if ( 'go-report' == $xpost->post->post_type )
				{
					$xpost->terms['go-type'] = $this->clean_go_type_research_terms( get_the_terms( $post_id, 'go-type', array( 'fields' => 'names' ) ) );
					
					// If this is a report parent post we need to make sure the children get updated too
					$report_children = go_reports()->get_report_children();

					// TODO: this is especially harsh when doing bulk xposting, we should maybe find a way to improve that
					foreach ( $report_children as $report_child )
					{
						// insert the child content into the parent
						// @TODO: this should be temporary, as it doesn't support the finding of sections as we'd hoped for
						$xpost->post->post_content .= "\n\n" . $report_child->post_title . "\n" . $report_child->post_content;

						// @TODO: this is commented out because we need to spend more time designing how to display the results
						// and because it's causing the xpost pull to time out
						//go_xpost()->process_post( $report_child->ID );
					} // END foreach
				} // END if
				elseif ( 'go-report-section' == $xpost->post->post_type )
				{
					// set the status based on the top-level parent
					$parent_report = go_reports()->get_current_report();
					$xpost->post->post_status = $parent_report->post_status;
					
					// Report sections need to get their go-type value from the parent report
					$xpost->terms['go-type'] = $this->clean_go_type_research_terms( get_the_terms( $parent_report->ID, 'go-type', array( 'fields' => 'names' ) ) );

					// set the publish times based on the top-level parent, rounding down to the nearest hour
					//
					// this string replace feels hackish, but it's less expensive than turning the string to a timestamp
					// and dealing with the risk of timezone conversions
					$xpost->post->post_date     = substr( $parent_report->post_date, 0, 12 ) . '0:00:00';
					$xpost->post->post_date_gmt = substr( $parent_report->post_date_gmt, 0, 12 ) . '0:00:00';

					// add the parent report's title as a prefix to the section title as in parent: child
					$xpost->post->post_title = trim( $parent_report->post_title ) . ': ' . trim( $xpost->post->post_title );

					// remove the parent ID and object
					$xpost->post->post_parent = 0;
					unset( $xpost->parent );
				} // END elseif

				// TODO: Remove this when we launch Research
				// Catch other types of reports, including, but not limited to:
				// briefings, research briefings, research notes, long views
				if ( ! count( $xpost->terms['go-type'] ) )
				{
					$xpost->terms['go-type'][] = 'Report';
				}
			} // END elseif
			// TODO: Remove go_shortpost when we launch Research
			elseif ( in_array( $xpost->post->post_type, array( 'post', 'go_shortpost' ) ) )
			{
				$xpost->terms['go-type'][] = 'Blog Post';
			} // END elseif
		} // END if

		if ( in_array( go_config()->get_property_slug(), array( 'gigaom', 'paidcontent' ) ) )
		{
			// special handling for excerpts on link posts
			$go_post = new GO_Theme_Post( $post );
			if ( $go_post->is_type( 'link' ) )
			{
				$content = preg_replace( $go_post->link_pattern, '', $xpost->post->post_content, 1 );
				$xpost->post->post_excerpt = trim( wp_trim_words( $content, 31, '' ), '.' ) . '&hellip;';
			}
			unset( $go_post );

			// GigaOM and paidContent channels are transformed into verticals
			// Pro has a channels taxonomy, but it includes a lot of accidental noise and isn't used
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

			// get the post content type
			// frustratingly, we have to look in multiple places for this
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
			else
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

		if ( 'go-datamodule' == $xpost->post->post_type )
		{
			// set the type and availability
			$xpost->terms['go-type'][] = 'Chart';
			$availability = 'Subscription';

			// remove the parent ID and object
			$xpost->post->post_parent = 0;
			unset( $xpost->parent );

			// remove the datamodule meta, as it's unused on Search.GO
			unset( $xpost->meta['data_set_v2'], $xpost->meta['data_set_v3'] );
		} // END if

		// Default go-type value in case it doesn't get set by something above? Maybe?
		if ( ! count( $xpost->terms['go-type'] ) )
		{
			$xpost->terms['go-type'][] = 'Blog Post';
		} // END else

		// search does not need the thumbnails
		foreach( $xpost->meta as $meta_key => $meta_values )
		{
			if ( FALSE !== strpos( $meta_key, '_thumbnail_id' ) )
			{
				unset( $xpost->meta[ $meta_key ] );
			}// end if
		}// end foreach

		// Set content availability
		$xpost->terms['go-availability'][] = $availability;

		// Remove any post_format if it exists
		unset( $xpost->terms['post_format'] );

		// don't send any category terms to search
		unset( $xpost->terms['category'] );

		// Force all post types to be a post for search
		$xpost->post->post_type = 'post';

		// Unset the ID value since setting it for the $post global resets it in the $xpost->post object
		unset( $xpost->post->ID );

		return $xpost;
	} // END post_filter

	/**
	 * Cleans up research version of go-type taxonomy terms for use in search
	 */
	public function clean_go_type_research_terms( array $terms )
	{
		$new_terms = array();
		
		foreach ( $terms as $term )
		{
			if ( ! isset( $term->name ) || 'Feature' == $term->name )
			{
				continue;
			} // END if
			
			$new_terms[] = $term->name;
		} // END foreach

		return $new_terms;
	} // END clean_go_type_research_terms
} // END GO_XPost_Filter_Search