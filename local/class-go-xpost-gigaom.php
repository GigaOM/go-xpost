<?php 

class GO_XPost_GigaOM extends GO_XPost
{
	/**
	 * XPost from GO to pC
	 *
	 * @param $post_id int Post ID
	 */
	public function process_post( $post_id )
	{
		// get the channels on the post
		$terms = wp_get_object_terms( $post_id, 'channel' );

		foreach( $terms as $term )
		{
			if ( 'media' != $term->slug )
			{
				continue;
			}//end if

			go_slog('go-xpost-start', 'XPost from GO to pC: START!',
				array(
					'post_id' => $post_id,
					'post_type' => get_post( $post_id )->post_type,
					'go_mancross_redirect' => get_post_meta( $post_id, 'go_mancross_redirect', TRUE ),
					'channel' => $term->slug,
				)
			);

			$this->push( $this->endpoint, $post_id );
		}//end foreach
	}//end process_post
}//end class
