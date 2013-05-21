<?php
require_once('PHPUnit/Framework/TestCase.php');

require_once(dirname(__FILE__) . '/../User.php');
require_once(dirname(__FILE__) . '/../db.php');

class UserTest extends PHPUnit_Framework_TestCase {
	public function testCreate() {
		User::create('Bob', 'bob@example.com', 'secret1234');
		$this->assertTrue(User::existsName('Bob'), 'Failed to create user "Bob" (no user with this name found)');
		$this->assertTrue(User::existsMail('bob@example.com'), 'Failed to create user "Bob" (no user with this mail address found)');
	}

	/**
	* @depends testCreate
	* @expectedException HttpException
	* @expectedExceptionCode 500
	*/
	public function testCreateDuplicateName() {
		User::create('Bob', 'bob2@example.com', 'some-pw');
	}

	/**
	* @depends testCreate
	* @expectedException HttpException
	* @expectedExceptionCode 500
	*/
	public function testCreateDuplicateMail() {
		User::create('Paul', 'bob@example.com', 'some-pw');
	}

	/**
	* @depends testCreate
	*/
	public function testID() {
		$id = User::getID('Bob');
		$this->assertRegExp('/[0-9a-fA-F]{32}/', $id, 'Invalid user ID for "Bob": "' . $id . '"');
	}

	/**
	* @depends testID
	*/
	public function testName() {
		$id = User::getID('Bob');
		$this->assertEquals(User::getName($id), 'Bob', 'Could not retrieve the user name for a given ID of "' . $id . '"');
	}

	/**
	* @depends testCreate
	*/
	public function testLogin() {
		$this->assertTrue(User::validateLogin('Bob', 'secret1234'), 'Could not login as "Bob" with password "secret1234"');
	}

	/**
	* @depends testID
	*/
	public function testPrivilegeBefore() {
		$this->assertEquals(User::getPrivileges(User::getID('Bob')), User::PRIVILEGE_NONE, 'User "Bob" should have zero privileges in the beginning.');
	}

	/**
	* @depends testCreate
	*/
	public function testDelete() {
		# not implemented yet
	}

	public static function tearDownAfterClass() { # HACK: can be removed once testDelete() is done
		$db_connection = db_ensure_connection();
		$db_query = 'DELETE FROM ' . DB_TABLE_USERS . ' WHERE `mail` = "bob@example.com"';
		$db_connection->query($db_query);
	}

	public function testPrivilegeArray() {
		$this->assertEquals(User::privilegeToArray(User::PRIVILEGE_NONE), array('none'), 'Failed to convert privilege PRIVILEGE_NONE to array');
		$this->assertEquals(User::privilegeToArray(User::PRIVILEGE_NONE|User::PRIVILEGE_REVIEW), array('review'), 'Failed to convert privilege PRIVILEGE_NONE|PRIVILEGE_REVIEW to array');

		$arr = User::privilegeToArray(User::PRIVILEGE_ADMIN|User::PRIVILEGE_STDLIB_ADMIN|User::PRIVILEGE_REGISTRATION);
		$this->assertInternalType('array', $arr, 'Privilege conversion did not return an array');
		$this->assertCount(3, $arr, 'Invalid element count in privilege array');
		$this->assertContains('admin', $arr, 'Failed to convert privilege PRIVILEGE_ADMIN|PRIVILEGE_STDLIB_ADMIN|PRIVILEGE_REGISTRATION to array');
		$this->assertContains('stdlib-admin', $arr, 'Failed to convert privilege PRIVILEGE_ADMIN|PRIVILEGE_STDLIB_ADMIN|PRIVILEGE_REGISTRATION to array');
		$this->assertContains('registration', $arr, 'Failed to convert privilege PRIVILEGE_ADMIN|PRIVILEGE_STDLIB_ADMIN|PRIVILEGE_REGISTRATION to array');
	}

	public function testArrayPrivilege() {
		$arr = array('stdlib', 'review');
		$this->assertEquals(User::privilegeFromArray($arr), User::PRIVILEGE_STDLIB|User::PRIVILEGE_REVIEW, 'Failed to convert array [stdlib, review] to privilege');

		$arr = array('admin');
		$this->assertEquals(User::privilegeFromArray($arr), User::PRIVILEGE_ADMIN, 'Failed to convert array [admin] to privilege');

		$arr = array();
		$this->assertEquals(User::privilegeFromArray($arr), User::PRIVILEGE_NONE, 'Failed to convert array [] to privilege');
	}
}
?>