<?php

function go_xpost_pre_save_post( $post )
{
	global $goxpost;

	foreach( (array) $post->meta as $mkey => $mval )
	{
		if( preg_match( '/^_go_channel_time-/', $mkey ))
		{
			$source_offset = $goxpost->utc_offset_from_dates($post->post->post_date_gmt, $post->post->post_date);

			$switched = (0 - $source_offset); // Flip the offset around so we can use it to get to UTC instead of Local

			$gmt_channel_time = $goxpost->utc_to_local($mval, $switched);

			$post->meta[ $mkey ] = $goxpost->utc_to_local( $gmt_channel_time );

			go_slog( 'go-xpost-debug', 'Adjusting channel time on '. $post_id .' '. $mval .' -> '. $post->meta[ $mkey ], $post );
		}//end if
	}//end foreach

	return $post;
}//end go_xpost_pre_save_post
