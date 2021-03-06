<?php

/**
 * @group option
 */
class Tests_Option_Option extends WP_UnitTestCase {

	function __return_foo() {
		return 'foo';
	}

	function test_the_basics() {
		$key = rand_str();
		$key2 = rand_str();
		$value = rand_str();
		$value2 = rand_str();

		$this->assertFalse( get_option( 'doesnotexist' ) );
		$this->assertTrue( add_option( $key, $value ) );
		$this->assertEquals( $value, get_option( $key ) );
		$this->assertFalse( add_option( $key, $value ) );  // Already exists
		$this->assertFalse( update_option( $key, $value ) );  // Value is the same
		$this->assertTrue( update_option( $key, $value2 ) );
		$this->assertEquals( $value2, get_option( $key ) );
		$this->assertFalse( add_option( $key, $value ) );
		$this->assertEquals( $value2, get_option( $key ) );
		$this->assertTrue( delete_option( $key ) );
		$this->assertFalse( get_option( $key ) );
		$this->assertFalse( delete_option( $key ) );

		$this->assertTrue( update_option( $key2, $value2 ) );
		$this->assertEquals( $value2, get_option( $key2 ) );
		$this->assertTrue( delete_option( $key2 ) );
		$this->assertFalse( get_option( $key2 ) );
	}

	function test_default_filter() {
		$random = rand_str();

		$this->assertFalse( get_option( 'doesnotexist' ) );

		// Default filter overrides $default arg.
		add_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );
		$this->assertEquals( 'foo', get_option( 'doesnotexist', 'bar' ) );

		// Remove the filter and the $default arg is honored.
		remove_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );
		$this->assertEquals( 'bar', get_option( 'doesnotexist', 'bar' ) );

		// Once the option exists, the $default arg and the default filter are ignored.
		add_option( 'doesnotexist', $random );
		$this->assertEquals( $random, get_option( 'doesnotexist', 'foo' ) );
		add_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );
		$this->assertEquals( $random, get_option( 'doesnotexist', 'foo' ) );
		remove_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );

		// Cleanup
		$this->assertTrue( delete_option( 'doesnotexist' ) );
		$this->assertFalse( get_option( 'doesnotexist' ) );
	}

	function test_serialized_data() {
		$key = rand_str();
		$value = array( 'foo' => true, 'bar' => true );

		$this->assertTrue( add_option( $key, $value ) );
		$this->assertEquals( $value, get_option( $key ) );

		$value = (object) $value;
		$this->assertTrue( update_option( $key, $value ) );
		$this->assertEquals( $value, get_option( $key ) );
		$this->assertTrue( delete_option( $key ) );
	}
}
