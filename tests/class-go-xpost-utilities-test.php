<?php
/**
 * GO_XPost_Utilities unit tests
 */

class GO_XPost_UtilitiesTest extends WP_UnitTestCase
{
	public $user1_id = NULL;
	public $user1_data = array(
		'user_login' => 'baconkat',
		'user_nicename' => 'baconkat',
		'user_email' => 'bacon.kat@gmail.com',
	);
	public $save_post_test_filename = 'save_post_test_data_01';

	/**
	 * this is run before each test* function in this class, to set
	 * up the environment each test runs in.
	 */
	public function setUp()
	{
		parent::setUp();

		register_taxonomy( 'technology', 'post', array( 'label' => 'technologies', ) );

		$this->user1_id = wp_insert_user( $this->user1_data );
	}//END setup

	/**
	 * test the save_post function, which saves a post retrieved
	 * from the source blog into the current blog.
	 */
	public function test_save_post()
	{
		// make sure our test data file is in
		$test_file = __DIR__ . '/' . $this->save_post_test_filename;
		$this->assertTrue( file_exists( $test_file ) );

		// read it in and unserialize it
		$raw_content = file_get_contents( $test_file );
		$this->assertTrue( FALSE !== $raw_content );

		$post = unserialize( $raw_content );
		$this->assertTrue( FALSE !== $post );

		$post_id = go_xpost_util()->save_post( $post );
		$this->assertTrue( 0 < $post_id );

		// get the author terms on the post
		$terms = wp_get_post_terms( $post_id, 'author' );
		$this->assertTrue( 0 < count( $terms ) );
		$this->assertEquals( 'kevintofel', $terms[0]->name );
	}//END test_save_post

	/**
	 * test for the case when we xpost terms like 'iPad 2' to a blog
	 * that contains term slugs with numeric suffixes such as 'ipad-2'
	 * for "iPad".
	 *
	 * see https://github.com/GigaOM/legacy-pro/issues/2118
	 */
	public function test_term_creation_with_numeric_suffixes()
	{
		// make sure our test data file is in
		$test_file = __DIR__ . '/' . $this->save_post_test_filename;
		$this->assertTrue( file_exists( $test_file ) );

		// read it in and unserialize it
		$raw_content = file_get_contents( $test_file );
		$this->assertTrue( FALSE !== $raw_content );

		$post = unserialize( $raw_content );
		$this->assertTrue( FALSE !== $post );

		// make sure we have the 'iPad 2' term in the source post
		$this->assertTrue( isset( $post->terms['technology'] ) );
		$this->assertTrue( 'iPad 2' == $post->terms['technology'][0] );

		// make sure we have 'ipad-2' slug mapped to 'ipad' on the
		// destination blog in the 'post_tag' taxonomy. to do that we
		// first need to create the "iPad" term in another taxonomy
		$term1 = $this->create_term( 'iPad', 'post_tag' );
		$this->assertFalse( empty( $term1 ) );

		// then rename the term so we'll get a slug collision later with
		// a new term with the same slug but different name
		$this->assertFalse( is_wp_error( wp_update_term( $term1->term_id, 'post_tag', array( 'name' => 'ipad' ) ) ) );

		// then create the same term again in a different taxonomy
		// to get a slug with a numeric suffix that will collide with
		// the "iPad 2" term
		$term2 = $this->create_term( 'iPad', 'technology' );
		$this->assertFalse( empty( $term2 ) );
		$this->assertEquals( 'ipad-2', $term2->slug );

		// save the post we loaded and check if the "iPad 2" technology term
		// is correctly mapped or not
		$post_id = go_xpost_util()->save_post( $post );
		$this->assertTrue( 0 < $post_id );

		// get the technology terms on the post
		$terms = wp_get_post_terms( $post_id, 'technology' );

		$this->assertEquals( 1, count( $terms ) );
		$this->assertEquals( 'iPad 2', $terms[0]->name );
	}//END test_term_creation_with_numeric_suffixes

	private function create_term( $name, $taxonomy )
	{
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( ! $term )
		{
			$new_term = wp_insert_term( $name, $taxonomy );
			$this->assertFalse( is_wp_error( $new_term  ) );
			$term = get_term( $new_term['term_id'], $taxonomy );
			$this->assertFalse( is_wp_error( $term ) );
			$this->assertTrue( is_object( $term ) );
		}
		return $term;
	}//END create_term
}// END GO_XPost_UtilitiesTest