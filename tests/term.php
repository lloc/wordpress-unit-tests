<?php

/**
 * @group taxonomy
 */
class Tests_Term extends WP_UnitTestCase {
	var $taxonomy = 'category';

	function setUp() {
		parent::setUp();
		// insert one term into every post taxonomy
		// otherwise term_ids and term_taxonomy_ids might be identical, which could mask bugs
		$term = rand_str();
		foreach(get_object_taxonomies('post') as $tax)
			wp_insert_term( $term, $tax );
	}

	function deleted_term_cb( $term, $tt_id, $taxonomy, $deleted_term ) {
		$this->assertInternalType( 'object', $deleted_term );
		$this->assertInternalType( 'int', $term );
		// Pesky string $this->assertInternalType( 'int', $tt_id );
		$this->assertEquals( $term, $deleted_term->term_id );
		$this->assertEquals( $taxonomy, $deleted_term->taxonomy );
		$this->assertEquals( $tt_id, $deleted_term->term_taxonomy_id );
	}

	function test_wp_insert_delete_term() {
		// a new unused term
		$term = rand_str();
		$this->assertNull( term_exists($term) );

		$initial_count = wp_count_terms( $this->taxonomy );

		$t = wp_insert_term( $term, $this->taxonomy );
		$this->assertInternalType( 'array', $t );
		$this->assertFalse( is_wp_error($t) );
		$this->assertTrue( $t['term_id'] > 0 );
		$this->assertTrue( $t['term_taxonomy_id'] > 0 );
		$this->assertEquals( $initial_count + 1, wp_count_terms($this->taxonomy) );

		// make sure the term exists
		$this->assertTrue( term_exists($term) > 0 );
		$this->assertTrue( term_exists($t['term_id']) > 0 );

		// now delete it
		add_filter( 'delete_term', array( $this, 'deleted_term_cb' ), 10, 4 );
		$this->assertTrue( wp_delete_term($t['term_id'], $this->taxonomy) );
		remove_filter( 'delete_term', array( $this, 'deleted_term_cb' ), 10, 4 );
		$this->assertNull( term_exists($term) );
		$this->assertNull( term_exists($t['term_id']) );
		$this->assertEquals( $initial_count, wp_count_terms($this->taxonomy) );
	}

	function test_term_exists_known() {
		// insert a term
		$term = rand_str();
		$t = wp_insert_term( $term, $this->taxonomy );
		$this->assertInternalType( 'array', $t );
		$this->assertEquals( $t['term_id'], term_exists($t['term_id']) );
		$this->assertEquals( $t['term_id'], term_exists($term) );

		// clean up
		$this->assertTrue( wp_delete_term($t['term_id'], $this->taxonomy) );
	}

	function test_term_exists_unknown() {
		$this->assertNull( term_exists(rand_str()) );
		$this->assertEquals( 0, term_exists(0) );
		$this->assertEquals( 0, term_exists('') );
		$this->assertEquals( 0, term_exists(NULL) );
	}

	/**
	 * @ticket 5381
	 */
	function test_is_term_type() {
		// insert a term
		$term = rand_str();
		$t = wp_insert_term( $term, $this->taxonomy );
		$this->assertInternalType( 'array', $t );
		$term_obj = get_term_by('name', $term, $this->taxonomy);
		$this->assertEquals( $t['term_id'], term_exists($term_obj->slug) );

		// clean up
		$this->assertTrue( wp_delete_term($t['term_id'], $this->taxonomy) );
	}

	function test_set_object_terms_by_id() {
		$ids = $this->factory->post->create_many(5);

		$terms = array();
		for ($i=0; $i<3; $i++ ) {
			$term = rand_str();
			$result = wp_insert_term( $term, $this->taxonomy );
			$this->assertInternalType( 'array', $result );
			$term_id[$term] = $result['term_id'];
		}

		foreach ($ids as $id) {
				$tt = wp_set_object_terms( $id, array_values($term_id), $this->taxonomy );
				// should return three term taxonomy ids
				$this->assertEquals( 3, count($tt) );
		}

		// each term should be associated with every post
		foreach ($term_id as $term=>$id) {
			$actual = get_objects_in_term($id, $this->taxonomy);
			$this->assertEquals( $ids, array_map('intval', $actual) );
		}

		// each term should have a count of 5
		foreach (array_keys($term_id) as $term) {
			$t = get_term_by('name', $term, $this->taxonomy);
			$this->assertEquals( 5, $t->count );
		}
	}

	function test_set_object_terms_by_name() {
		$ids = $this->factory->post->create_many(5);

		$terms = array(
				rand_str(),
				rand_str(),
				rand_str());

		foreach ($ids as $id) {
				$tt = wp_set_object_terms( $id, $terms, $this->taxonomy );
				// should return three term taxonomy ids
				$this->assertEquals( 3, count($tt) );
				// remember which term has which term_id
				for ($i=0; $i<3; $i++) {
					$term = get_term_by('name', $terms[$i], $this->taxonomy);
					$term_id[$terms[$i]] = intval($term->term_id);
				}
		}

		// each term should be associated with every post
		foreach ($term_id as $term=>$id) {
			$actual = get_objects_in_term($id, $this->taxonomy);
			$this->assertEquals( $ids, array_map('intval', $actual) );
		}

		// each term should have a count of 5
		foreach ($terms as $term) {
			$t = get_term_by('name', $term, $this->taxonomy);
			$this->assertEquals( 5, $t->count );
		}
	}

	function test_change_object_terms_by_name() {
		// set some terms on an object; then change them while leaving one intact

		$post_id = $this->factory->post->create();

		$terms_1 = array('foo', 'bar', 'baz');
		$terms_2 = array('bar', 'bing');

		// set the initial terms
		$tt_1 = wp_set_object_terms( $post_id, $terms_1, $this->taxonomy );
		$this->assertEquals( 3, count($tt_1) );

		// make sure they're correct
		$terms = wp_get_object_terms($post_id, $this->taxonomy, array('fields' => 'names', 'orderby' => 't.term_id'));
		$this->assertEquals( $terms_1, $terms );

		// change the terms
		$tt_2 = wp_set_object_terms( $post_id, $terms_2, $this->taxonomy );
		$this->assertEquals( 2, count($tt_2) );

		// make sure they're correct
		$terms = wp_get_object_terms($post_id, $this->taxonomy, array('fields' => 'names', 'orderby' => 't.term_id'));
		$this->assertEquals( $terms_2, $terms );

		// make sure the tt id for 'bar' matches
		$this->assertEquals( $tt_1[1], $tt_2[0] );

	}

	/**
	 * @ticket 22560
	 */
	function test_object_term_cache() {
		$post_id = $this->factory->post->create();

		$terms_1 = array('foo', 'bar', 'baz');
		$terms_2 = array('bar', 'bing');

		// Cache should be empty after a set.
		$tt_1 = wp_set_object_terms( $post_id, $terms_1, $this->taxonomy );
		$this->assertEquals( 3, count($tt_1) );
		$this->assertFalse( wp_cache_get( $post_id, $this->taxonomy . '_relationships') );

		// wp_get_object_terms() does not prime the cache.
		wp_get_object_terms( $post_id, $this->taxonomy, array('fields' => 'names', 'orderby' => 't.term_id') );
		$this->assertFalse( wp_cache_get( $post_id, $this->taxonomy . '_relationships') );

		// get_the_terms() does prime the cache.
		$terms = get_the_terms( $post_id, $this->taxonomy );
		$cache = wp_cache_get( $post_id, $this->taxonomy . '_relationships');
		$this->assertInternalType( 'array', $cache );

		// Cache should be empty after a set.
		$tt_2 = wp_set_object_terms( $post_id, $terms_2, $this->taxonomy );
		$this->assertEquals( 2, count($tt_2) );
		$this->assertFalse( wp_cache_get( $post_id, $this->taxonomy . '_relationships') );
	}

	function test_change_object_terms_by_id() {
		// set some terms on an object; then change them while leaving one intact

		$post_id = $this->factory->post->create();

		// first set: 3 terms
		$terms_1 = array();
		for ($i=0; $i<3; $i++ ) {
			$term = rand_str();
			$result = wp_insert_term( $term, $this->taxonomy );
			$this->assertInternalType( 'array', $result );
			$terms_1[$i] = $result['term_id'];
		}

		// second set: one of the original terms, plus one new term
		$terms_2 = array();
		$terms_2[0] = $terms_1[1];

		$term = rand_str();
		$result = wp_insert_term( $term, $this->taxonomy );
		$terms_2[1] = $result['term_id'];


		// set the initial terms
		$tt_1 = wp_set_object_terms( $post_id, $terms_1, $this->taxonomy );
		$this->assertEquals( 3, count($tt_1) );

		// make sure they're correct
		$terms = wp_get_object_terms($post_id, $this->taxonomy, array('fields' => 'ids', 'orderby' => 't.term_id'));
		$this->assertEquals( $terms_1, $terms );

		// change the terms
		$tt_2 = wp_set_object_terms( $post_id, $terms_2, $this->taxonomy );
		$this->assertEquals( 2, count($tt_2) );

		// make sure they're correct
		$terms = wp_get_object_terms($post_id, $this->taxonomy, array('fields' => 'ids', 'orderby' => 't.term_id'));
		$this->assertEquals( $terms_2, $terms );

		// make sure the tt id for 'bar' matches
		$this->assertEquals( $tt_1[1], $tt_2[0] );

	}

	function test_get_object_terms_by_slug() {
		$post_id = $this->factory->post->create();

		$terms_1 = array('Foo', 'Bar', 'Baz');
		$terms_1_slugs = array('foo', 'bar', 'baz');

		// set the initial terms
		$tt_1 = wp_set_object_terms( $post_id, $terms_1, $this->taxonomy );
		$this->assertEquals( 3, count($tt_1) );

		// make sure they're correct
		$terms = wp_get_object_terms($post_id, $this->taxonomy, array('fields' => 'slugs', 'orderby' => 't.term_id'));
		$this->assertEquals( $terms_1_slugs, $terms );
	}

	function test_set_object_terms_invalid() {
		$post_id = $this->factory->post->create();

		// bogus taxonomy
		$result = wp_set_object_terms( $post_id, array(rand_str()), rand_str() );
		$this->assertTrue( is_wp_error($result) );
	}

	function test_term_is_ancestor_of( ) {
		$term = rand_str();
		$term2 = rand_str();

		$t = wp_insert_term( $term, 'category' );
		$this->assertInternalType( 'array', $t );
		$t2 = wp_insert_term( $term, 'category', array( 'parent' => $t['term_id'] ) );
		$this->assertInternalType( 'array', $t2 );
		if ( function_exists( 'term_is_ancestor_of' ) ) {
			$this->assertTrue( term_is_ancestor_of( $t['term_id'], $t2['term_id'], 'category' ) );
			$this->assertFalse( term_is_ancestor_of( $t2['term_id'], $t['term_id'], 'category' ) );
		}
		$this->assertTrue( cat_is_ancestor_of( $t['term_id'], $t2['term_id']) );
		$this->assertFalse( cat_is_ancestor_of( $t2['term_id'], $t['term_id']) );

		wp_delete_term($t['term_id'], 'category');
		wp_delete_term($t2['term_id'], 'category');
	}

	function test_wp_insert_delete_category() {
		$term = rand_str();
		$this->assertNull( category_exists( $term ) );

		$initial_count = wp_count_terms( 'category' );

		$t = wp_insert_category( array( 'cat_name' => $term ) );
		$this->assertTrue( is_numeric($t) );
		$this->assertFalse( is_wp_error($t) );
		$this->assertTrue( $t > 0 );
		$this->assertEquals( $initial_count + 1, wp_count_terms( 'category' ) );

		// make sure the term exists
		$this->assertTrue( term_exists($term) > 0 );
		$this->assertTrue( term_exists($t) > 0 );

		// now delete it
		$this->assertTrue( wp_delete_category($t) );
		$this->assertNull( term_exists($term) );
		$this->assertNull( term_exists($t) );
		$this->assertEquals( $initial_count, wp_count_terms('category') );
	}

	function test_wp_unique_term_slug() {
		// set up test data
		$a = wp_insert_term( 'parent', $this->taxonomy );
		$this->assertInternalType( 'array', $a );
		$b = wp_insert_term( 'child',  $this->taxonomy, array( 'parent' => $a['term_id'] ) );
		$this->assertInternalType( 'array', $b );
		$c = wp_insert_term( 'neighbor', $this->taxonomy );
		$this->assertInternalType( 'array', $c );
		$d = wp_insert_term( 'pet',  $this->taxonomy, array( 'parent' => $c['term_id'] )  );
		$this->assertInternalType( 'array', $c );

		$a_term = get_term( $a['term_id'], $this->taxonomy );
		$b_term = get_term( $b['term_id'], $this->taxonomy );
		$c_term = get_term( $c['term_id'], $this->taxonomy );
		$d_term = get_term( $d['term_id'], $this->taxonomy );

		// a unique slug gets unchanged
		$this->assertEquals( 'unique-term', wp_unique_term_slug( 'unique-term', $c_term ) );

		// a non-hierarchicial dupe gets suffixed with "-#"
		$this->assertEquals( 'parent-2', wp_unique_term_slug( 'parent', $c_term ) );

		// a hierarchical dupe initially gets suffixed with its parent term
		$this->assertEquals( 'child-neighbor', wp_unique_term_slug( 'child', $d_term ) );

		// a hierarchical dupe whose parent already contains the {term}-{parent term}
		// term gets suffixed with parent term name and then '-#'
		$e = wp_insert_term( 'child-neighbor', $this->taxonomy, array( 'parent' => $c['term_id'] ) );
		$this->assertEquals( 'child-neighbor-2', wp_unique_term_slug( 'child', $d_term ) );

		// clean up
		foreach ( array( $a, $b, $c, $d, $e ) as $t )
			$this->assertTrue( wp_delete_term( $t['term_id'], $this->taxonomy ) );
	}

	/**
	 * @ticket 5809
	 */
	function test_update_shared_term() {
		$random_tax = __FUNCTION__;

		register_taxonomy( $random_tax, 'post' );

		$post_id = $this->factory->post->create();

		$old_name = 'Initial';

		$t1 = wp_insert_term( $old_name, 'category' );
		$t2 = wp_insert_term( $old_name, 'post_tag' );

		$this->assertEquals( $t1['term_id'], $t2['term_id'] );

		wp_set_post_categories( $post_id, array( $t1['term_id'] ) );
		wp_set_post_tags( $post_id, array( (int) $t2['term_id'] ) );

		$new_name = 'Updated';

		// create the term in a third taxonomy, just to keep things interesting
		$t3 = wp_insert_term( $old_name, $random_tax );
		wp_set_post_terms( $post_id, array( (int) $t3['term_id'] ), $random_tax );
		$this->assertPostHasTerms( $post_id, array( $t3['term_id'] ), $random_tax );

		$t2_updated = wp_update_term( $t2['term_id'], 'post_tag', array(
			'name' => $new_name
		) );

		$this->assertNotEquals( $t2_updated['term_id'], $t3['term_id'] );

		// make sure the terms have split
		$this->assertEquals( $old_name, get_term_field( 'name', $t1['term_id'], 'category' ) );
		$this->assertEquals( $new_name, get_term_field( 'name', $t2_updated['term_id'], 'post_tag' ) );

		// and that they are still assigned to the correct post
		$this->assertPostHasTerms( $post_id, array( $t1['term_id'] ), 'category' );
		$this->assertPostHasTerms( $post_id, array( $t2_updated['term_id'] ), 'post_tag' );
		$this->assertPostHasTerms( $post_id, array( $t3['term_id'] ), $random_tax );

		// clean up
		unset( $GLOBALS['wp_taxonomies'][ $random_tax ] );
	}

	private function assertPostHasTerms( $post_id, $expected_term_ids, $taxonomy ) {
		$assigned_term_ids = wp_get_object_terms( $post_id, $taxonomy, array(
			'fields' => 'ids'
		) );

		$this->assertEquals( $expected_term_ids, $assigned_term_ids );
	}
}
