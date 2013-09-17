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

}// END GO_XPost_UtilitiesTest