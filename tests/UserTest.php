<?php
require_once('PHPUnit/Autoload.php');
require_once('PHPUnit/Framework/Assert/Functions.php'); # should not be required (?)

require_once(dirname(__FILE__) . '/../User.php');
require_once(dirname(__FILE__) . '/../db.php');

class UserTest extends PHPUnit_Framework_TestCase {
	public static function testCreate() {
		User::create('Bob', 'bob@example.com', 'secret1234');
		assertTrue(User::existsName('Bob'), 'Failed to create user "Bob" (no user with this name found)');
		assertTrue(User::existsMail('bob@example.com'), 'Failed to create user "Bob" (no user with this mail address found)');
	}

	/**
	* @depends testCreate
	* @expectedException HttpException
	* @expectedExceptionCode 500
	*/
	public static function testCreateDuplicateName() {
		User::create('Bob', 'bob2@example.com', 'some-pw');
	}

	/**
	* @depends testCreate
	* @expectedException HttpException
	* @expectedExceptionCode 500
	*/
	public static function testCreateDuplicateMail() {
		User::create('Paul', 'bob@example.com', 'some-pw');
	}

	/**
	* @depends testCreate
	*/
	public static function testID() {
		$id = User::getID('Bob');
		assertRegExp('/[0-9a-fA-F]{32}/', $id, 'Invalid user ID for "Bob": "' . $id . '"');
	}

	/**
	* @depends testID
	*/
	public static function testName() {
		$id = User::getID('Bob');
		assertEquals(User::getName($id), 'Bob', 'Could not retrieve the user name for a given ID of "' . $id . '"');
	}

	/**
	* @depends testCreate
	*/
	public static function testLogin() {
		assertTrue(User::validateLogin('Bob', 'secret1234'), 'Could not login as "Bob" with password "secret1234"');
	}

	/**
	* @depends testID
	*/
	public static function testPrivilegeBefore() {
		assertEquals(User::getPrivileges(User::getID('Bob')), User::PRIVILEGE_NONE, 'User "Bob" should have zero privileges in the beginning.');
	}

	/**
	* @depends testCreate
	*/
	public static function testDelete() {
		# not implemented yet
	}

	public static function tearDownAfterClass() { # HACK: can be removed once testDelete() is done
		$db_connection = db_ensure_connection();
		$db_query = 'DELETE FROM ' . DB_TABLE_USERS . ' WHERE `mail` = "bob@example.com"';
		$db_connection->query($db_query);
	}

	public static function testPrivilegeArray() {
		assertEquals(User::privilegeToArray(User::PRIVILEGE_NONE), array('none'), 'Failed to convert privilege PRIVILEGE_NONE to array');
		assertEquals(User::privilegeToArray(User::PRIVILEGE_NONE|User::PRIVILEGE_REVIEW), array('review'), 'Failed to convert privilege PRIVILEGE_NONE|PRIVILEGE_REVIEW to array');

		$arr = User::privilegeToArray(User::PRIVILEGE_ADMIN|User::PRIVILEGE_STDLIB_ADMIN|User::PRIVILEGE_REGISTRATION);
		assertInternalType('array', $arr, 'Privilege conversion did not return an array');
		assertCount(3, $arr, 'Invalid element count in privilege array');
		assertContains('admin', $arr, 'Failed to convert privilege PRIVILEGE_ADMIN|PRIVILEGE_STDLIB_ADMIN|PRIVILEGE_REGISTRATION to array');
		assertContains('stdlib-admin', $arr, 'Failed to convert privilege PRIVILEGE_ADMIN|PRIVILEGE_STDLIB_ADMIN|PRIVILEGE_REGISTRATION to array');
		assertContains('registration', $arr, 'Failed to convert privilege PRIVILEGE_ADMIN|PRIVILEGE_STDLIB_ADMIN|PRIVILEGE_REGISTRATION to array');
	}

	public static function testArrayPrivilege() {
		$arr = array('stdlib', 'review');
		assertEquals(User::privilegeFromArray($arr), User::PRIVILEGE_STDLIB|User::PRIVILEGE_REVIEW, 'Failed to convert array [stdlib, review] to privilege');

		$arr = array('admin');
		assertEquals(User::privilegeFromArray($arr), User::PRIVILEGE_ADMIN, 'Failed to convert array [admin] to privilege');

		$arr = array();
		assertEquals(User::privilegeFromArray($arr), User::PRIVILEGE_NONE, 'Failed to convert array [] to privilege');
	}
}
?>