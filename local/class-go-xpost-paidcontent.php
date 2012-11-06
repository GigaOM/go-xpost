<?php

class GO_XPost_Paidcontent extends GO_XPost
{
	/**
	 * XPost from pC to GO
	 *
	 * @param $post_id int Post ID
	 */
	public function process_post( $post_id )
	{
		go_slog('go-xpost-start', 'XPost from pC to GO: START!',
			array(
				'post_id' => $post_id,
				'post_type' => get_post( $post_id )->post_type,
				'go_mancross_redirect' => get_post_meta( $post_id, 'go_mancross_redirect', TRUE ),
			)
		);

		// push all posts
		$this->push( $this->endpoint, $post_id );

		return;
	}//end process_post
}//end class
