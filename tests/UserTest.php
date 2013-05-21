<?php
require_once(dirname(__FILE__) . '/ALD_Database_TestCase.php');

require_once(dirname(__FILE__) . '/../config/database.php');
require_once(dirname(__FILE__) . '/../User.php');

class UserTest extends ALD_Database_TestCase {
	const NoviceUser_ID = '016E411164A84F51BDD03D13BD4D991E';

	public function getDataSet() {
		return $this->createMySQLXMLDataSet(dirname(__FILE__) . '/database/UserTest.mysql.xml');
	}

	public function test_existsName() {
		$this->assertTrue(User::existsName('NoviceUser'), 'User::existsName() failed on user "NoviceUser"');
	}

	public function test_existsMail() {
		$this->assertTrue(User::existsMail('me@example.com'), 'User::existsMail() failed on user "NoviceUser"');
	}

	public function test_validateLogin() {
		try {
			User::validateLogin('NoviceUser', 'justsomepw');
		} catch (HttpException $e) {
			$this->fail('Could not login as "NoviceUser" with password "justsomepw"');
		}
	}

	public function test_getID() {
		$id = User::getID('NoviceUser');
		$this->assertEquals(self::NoviceUser_ID, $id, 'Wrong user ID for "NoviceUser": "' . $id . '"');
	}

	public function test_getName() {
		try {
			$this->assertEquals(User::getName(self::NoviceUser_ID), 'NoviceUser', 'User::getName() returned the wrong name');
		} catch (HttpException $e) {
			$this->fail('User::getName() could not retrieve the name for ID "' . $id . '"');
		}
	}

	public function test_create() {
		$count_before = $this->getConnection()->getRowCount(DB_TABLE_USERS);
		User::create('Bob', 'bob@example.com', 'secret1234');
		$this->assertEquals($count_before + 1, $this->getConnection()->getRowCount(DB_TABLE_USERS));
	}

	/*
	* @depends test_create
	* @depends test_existsName
	* @depends test_existsMail
	* @depends test_getID
	*/
	public function test_create__valid() {
		$count_before = $this->getConnection()->getRowCount(DB_TABLE_USERS);

		User::create('Bob', 'bob@example.com', 'secret1234');
		$this->assertEquals($count_before + 1, $this->getConnection()->getRowCount(DB_TABLE_USERS));

		$this->assertTrue(User::existsName('Bob'), 'No user with name "Bob" was found');
		$this->assertTrue(User::existsMail('bob@example.com'), 'No user with mail "bob@example.com" was found');

		$id = User::getID('Bob');
		$this->assertRegExp('/[0-9a-fA-F]{32}/', $id, 'Invalid user ID for "Bob": "' . $id . '"');
	}

	/**
	* @depends test_create
	* @expectedException HttpException
	* @expectedExceptionCode 500
	*/
	public function test_create__duplicate_name() {
		User::create('NoviceUser', 'bob2@example.com', 'some-pw');
	}

	/**
	* @depends test_create
	* @expectedException HttpException
	* @expectedExceptionCode 500
	*/
	public function test_create__duplicate_mail() {
		User::create('Paul', 'me@example.com', 'some-pw');
	}

	public function testDelete() {
		# not implemented yet
	}

	public function test_getPrivileges__before() {
		$this->assertEquals(User::getPrivileges(self::NoviceUser_ID), User::PRIVILEGE_NONE, 'User "NoviceUser" should have zero privileges in the beginning.');
	}

	public function test_privilegeToArray() {
		$this->assertEquals(User::privilegeToArray(User::PRIVILEGE_NONE), array('none'), 'Failed to convert privilege PRIVILEGE_NONE to array');
		$this->assertEquals(User::privilegeToArray(User::PRIVILEGE_NONE|User::PRIVILEGE_REVIEW), array('review'), 'Failed to convert privilege PRIVILEGE_NONE|PRIVILEGE_REVIEW to array');

		$arr = User::privilegeToArray(User::PRIVILEGE_ADMIN|User::PRIVILEGE_STDLIB_ADMIN|User::PRIVILEGE_REGISTRATION);
		$this->assertInternalType('array', $arr, 'Privilege conversion did not return an array');
		$this->assertCount(3, $arr, 'Invalid element count in privilege array');
		$this->assertContains('admin', $arr, 'Failed to convert privilege PRIVILEGE_ADMIN|PRIVILEGE_STDLIB_ADMIN|PRIVILEGE_REGISTRATION to array');
		$this->assertContains('stdlib-admin', $arr, 'Failed to convert privilege PRIVILEGE_ADMIN|PRIVILEGE_STDLIB_ADMIN|PRIVILEGE_REGISTRATION to array');
		$this->assertContains('registration', $arr, 'Failed to convert privilege PRIVILEGE_ADMIN|PRIVILEGE_STDLIB_ADMIN|PRIVILEGE_REGISTRATION to array');
	}

	public function test_privilegeFromArray() {
		$arr = array('stdlib', 'review');
		$this->assertEquals(User::privilegeFromArray($arr), User::PRIVILEGE_STDLIB|User::PRIVILEGE_REVIEW, 'Failed to convert array [stdlib, review] to privilege');

		$arr = array('admin');
		$this->assertEquals(User::privilegeFromArray($arr), User::PRIVILEGE_ADMIN, 'Failed to convert array [admin] to privilege');

		$arr = array();
		$this->assertEquals(User::privilegeFromArray($arr), User::PRIVILEGE_NONE, 'Failed to convert array [] to privilege');
	}
}
?>