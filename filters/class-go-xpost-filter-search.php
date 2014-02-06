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
		} // end if

		// check for valid post types
		$valid_post_types = array(
			'go-datamodule',
			'go-events-event',
			'go-events-session',
			'go-report',
			// 'go-report-section', // temporarily disabled, need to come up with a better plan
			'go_shortpost',
			'go_webinar',
			'post',
		);

		if ( ! in_array( $post->post_type, $valid_post_types ) )
		{
			return FALSE;
		} // end if

		//only include events and sessions from the events property

		if ( 'events' == go_config()->get_property_slug() )
		{
			if ( 'go-events-event' != $post->post_type && 'go-events-session' != $post->post_type )
			{
				return FALSE;
			}// end if
		}// end if

		// only include charts from the research property
		if (
			'go-datamodule' == $post->post_type &&
			'research' != go_config()->get_property_slug()
		)
		{
			return FALSE;
		} // end if

		// exclude report subsections that are about the author or about gigaom research
		if (
			'go-report-section' == $post->post_type &&
			'research' == go_config()->get_property_slug()
		)
		{
			if (
				0 === stripos( $post->post_title, 'about gigaom' ) || // match "About Gigaom..."
				(
					0 === stripos( $post->post_title, 'about ' ) && // match "About Keren Elazari"
					5 < $post->menu_order // exclude early sections, which might begin with "about" for other reasons
				)
			)
			{
				return FALSE;

			}//end if
		}//end if

		// exclude some categories from Pro
		if ( 'research' != go_config()->get_property_slug() )
		{
			$invalid_categories = array(
				'links',          // We don't want curated links from pro going into search
				'poll-summaries', // Same for poll summaries
			);

			$categories = get_the_terms( $post_id, 'category' );
			if ( $categories && ! is_wp_error( $categories ) )
			{
				foreach ( $categories as $category )
				{
					if ( in_array( $category->slug, $invalid_categories ) )
					{
						return FALSE;
					} // end if
				} // end foreach
			}//end if
		}// end if

		return TRUE;
	} // end should_send_post

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

		// strip out any and all shortcodes
		$xpost->post->post_content = preg_replace( '/(\[.*?\])/', '', $xpost->post->post_content );

		// shorten the post excerpt if we have one (preserve the full excerpt in the post content, so we can still search it)
		if ( ! empty( $xpost->post->post_excerpt ) )
		{
			$xpost->post->post_content = $xpost->post->post_excerpt . "\n\n" . $xpost->post->post_content;
			$xpost->post->post_excerpt = trim( wp_trim_words( $xpost->post->post_content, 31, '' ), '.' ) . '&hellip;';
		}//end if
		// or generate a post excerpt if we don't have one (saves us from having to generate it when displaying the posts)
		else
		{
			$xpost->post->post_excerpt = trim( wp_trim_words( $xpost->post->post_content, 31, '' ), '.' ) . '&hellip;';
		}// end else

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

				// set future scheduled webinars to published so they can appear in search
				if ( 'future' == $xpost->post->post_status )
				{
					$xpost->post->post_status = 'publish';
				}// end if

				// remove the go_webinar meta, as it's unused on Search.GO
				unset( $xpost->meta['go_webinar'] );
			} // end if
			elseif ( 0 == strncmp( 'go-report', $xpost->post->post_type, 9 ) )
			{
				// set the availability up top
				// we may need to reconsider this if we use the report plugin on Gigaom or other non-subscriber sites
				$availability = 'Subscription';

				// Set go-type value for go-report and go-report-section types
				if ( 'go-report' == $xpost->post->post_type )
				{
					$go_type_terms = get_the_terms( $post_id, 'go-type' );
					
					if ( $go_type_terms && ! is_wp_error( $go_type_terms ) )
					{
						$xpost->terms['go-type'] = $this->clean_go_type_research_terms( $go_type_terms );
					} // END if

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
					} // end foreach
				} // end if
				elseif ( 'go-report-section' == $xpost->post->post_type )
				{
					// set the status based on the top-level parent
					$parent_report = go_reports()->get_current_report();
					$xpost->post->post_status = $parent_report->post_status;

					$go_type_terms = get_the_terms( $parent_report->ID, 'go-type' );
					
					if ( $go_type_terms && ! is_wp_error( $go_type_terms ) )
					{
						$xpost->terms['go-type'] = $this->clean_go_type_research_terms( $go_type_terms );
					} // END if

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
					unset( $xpost->post->post_parent );
				} // end elseif


			} // end elseif
		} // end if

		if ( 'gigaom' == go_config()->get_property_slug() )
		{
			// special handling for excerpts on link posts
			$go_post = new GO_Theme_Post( $post );
			if ( $go_post->is_type( 'link' ) )
			{
				$content = preg_replace( $go_post->link_pattern, '', $xpost->post->post_content, 1 );
				$xpost->post->post_excerpt = trim( wp_trim_words( $content, 31, '' ), '.' ) . '&hellip;';
			}// end if
			unset( $go_post );

			// Gigaom and paidContent channels are transformed into verticals
			// Pro has a channels taxonomy, but it includes a lot of accidental noise and isn't used
			if ( isset( $xpost->terms['channel'] ) )
			{
				foreach ( $xpost->terms['channel'] as $channel )
				{
					$xpost->terms['vertical'][] = ucwords( $channel );
				} // end foreach
			} // end if

			if ( isset( $xpost->terms['primary_channel'] ) )
			{
				foreach ( $xpost->terms['primary_channel'] as $primary_channel )
				{
					$xpost->terms['vertical'][] = ucwords( $primary_channel );
				} // end foreach
			} // end if

			// get the post content type
			// frustratingly, we have to look in multiple places for this
			if (
				'video' == go_waterfall_options()->get_type( $post_id )
				|| ( isset( $xpost->terms['go_syn_media'] ) && in_array( 'video', $xpost->terms['go_syn_media'] ) )
			)
			{
				$xpost->terms['go-type'][] = 'Video';
			} // end if
			elseif (
				'audio' == go_waterfall_options()->get_type( $post_id )
				|| ( isset( $xpost->terms['go_syn_media'] ) && in_array( 'podcast', $xpost->terms['go_syn_media'] ) )
			)
			{
				$xpost->terms['go-type'][] = 'Audio';
			} // end elseif
			else
			{
				$xpost->terms['go-type'][] = 'Blog Post';
			} // end else

			// multi-page posts with 3 OR MORE pages on GO/pC are also reports
			if ( preg_match_all( '/--nextpage--/', $xpost->post->post_content, $matches ) )
			{
				if ( 2 <= count( $matches ) )
				{
					$xpost->terms['go-type'][] = 'Report';
				} // end if
			} // end if
		} // end if

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
		} // end if

		if ( 'events' == go_config()->get_property_slug() )
		{
			//set the event
			$_REQUEST['post'] = $post_id;
			$event = go_events()->event()->get_the_event();
			$xpost->terms['go-type'][] = 'Event';

			if ( 'go-events-event' == $xpost->post->post_type )
			{
				// get the terms for the event
				foreach ( ( array ) wp_get_object_terms( $post_id, get_object_taxonomies( $xpost->post->post_type ) ) as $term )
				{
					$xpost->terms[ $term->taxonomy ][] = $term->name;
				}// end foreach

				$sessions = get_children( 'post_parent=' . $post_id . '&post_type=go-events-session' );

				//get taxonomy list from first session
				$session_taxonomies = get_object_taxonomies( $sessions[0]->post_type );

				foreach ( $sessions as $session )
				{
					//get taxonomy list from the session
					$session_terms = get_object_taxonomies( $session->post_type );
					if ( ! empty( $session_terms ) && ! is_wp_error( $session_terms ) )
					{
						// get the terms for the each session and add to the event?
						foreach ( $session_terms as $term )
						{
							$xpost->terms[ $term->taxonomy ][] = $term->name;
						}//end foreach
					}//end if
				}//end foreach

				// set the content
				$xpost->post->post_content = $xpost->post->post_excerpt . go_events()->event()->get_meta( $post_id )->tagline;

				// set event start datetime:
				$start = new DateTime( go_events()->event()->get_meta( $post_id )->start );
				
			}// end if
			elseif ( 'go-events-session' == $xpost->post->post_type )
			{
				$xpost->terms['go-type'][] = 'Event Session';
				// get the terms
				foreach ( ( array ) wp_get_object_terms( $post_id, get_object_taxonomies( $xpost->post->post_type ) ) as $term )
				{
					$xpost->terms[ $term->taxonomy ][] = $term->name;
				}// end foreach

				// then make sure each session speaker is also in there (if they aren't added as a person term)
				$speakers = go_events()->event()->session()->get_speakers( $post_id );
				foreach ( $speakers as $speaker )
				{
					if ( ! is_array( $xpost->terms['person'] ) || ! in_array( $speaker->post_title, $xpost->terms['person'] ) )
					{
						$xpost->terms['person'][] = $speaker->post_title;
					}// end if
				}// end foreach

				// set session start datetime:
				$start = new DateTime( go_events()->event()->session()->get_meta( $post_id )->start );
			}// end else

			// set post_date and post_date_gmt to a non-future date:
			$start_date = ( new DateTime() < $start ) ? $start : new DateTime( $xpost->post->post_modified );
			$xpost->post->post_date = $start_date->format( 'Y-m-d H:i:s' );
			$start_date->setTimezone( new DateTimeZone( 'GMT' ) );
			$xpost->post->post_date_gmt = $start_date->format( 'Y-m-d H:i:s' );

			$xpost->post->author = 'support+gigaedit@gigaom.com';

			// set future scheduled event/session status to published so they can appear in search
			if ( 'future' == $xpost->post->post_status )
			{
				$xpost->post->post_status = 'publish';
			}// end if
		}// end if

		// Default go-type value in case it doesn't get set by something above? Maybe?
		if ( ! count( $xpost->terms['go-type'] ) )
		{
			$xpost->terms['go-type'][] = 'Blog Post';
		} // end else

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
	} // end post_filter

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
			} // end if

			$new_terms[] = $term->name;
		} // end foreach

		return $new_terms;
	} // end clean_go_type_research_terms
}// end GO_XPost_Filter_Search