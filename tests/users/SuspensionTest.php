<?php
require_once 'PHPUnit/Framework/TestCase.php';

require_once dirname(__FILE__) . '/../../User.php';
require_once dirname(__FILE__) . '/../../db.php';
require_once dirname(__FILE__) . '/../../users/Suspension.php';

class SuspensionTest extends PHPUnit_Framework_TestCase {

	private static $id;
	private static $s1;
	private static $s2;

	const USER_NAME = 'Frank';
	const EXPIRATION_DATE = '2037-04-15 00:03:49';

	public static function setUpBeforeClass() {
		User::create(self::USER_NAME, 'frank@example.com', '1234');
		self::$id = User::getID(self::USER_NAME);
	}

	public static function tearDownAfterClass() {
		$db_connection = db_ensure_connection();

		$db_query = 'DELETE FROM ' . DB_TABLE_USERS . ' WHERE `name` = "' . self::USER_NAME . '"';
		$db_connection->query($db_query);

		$db_query = 'DELETE FROM ' . DB_TABLE_SUSPENSIONS . ' WHERE `user` = UNHEX("' . self::$id . '")';
		$db_connection->query($db_query);
	}

	public function testBeforeSuspension() {
		$this->assertFalse(Suspension::isSuspended(self::USER_NAME));
		$this->assertFalse(Suspension::isSuspendedById(self::$id));
	}

	/**
	* @depends testBeforeSuspension
	*/
	public function testCreate() {
		self::$s1 = Suspension::create(self::USER_NAME, 'Suspended for no particular reason');
		$this->assertInternalType('int', self::$s1, 'Suspension creation did not return an integer');
		$this->assertTrue(Suspension::isSuspended(self::USER_NAME));
	}

	/**
	* @depends testBeforeSuspension
	*/
	public function testCreateForId() {
		self::$s2 = Suspension::createForId(self::$id, 'For testing reasons', self::EXPIRATION_DATE);
		$this->assertInternalType('int', self::$s2, 'Suspension creation (for ID) did not return an integer');
		$this->assertTrue(Suspension::isSuspendedById(self::$id));
	}

	/**
	* @depends testCreate
	* @depends testCreateForId
	*/
	public function testRetrieveList() {
		$s = Suspension::getSuspensions(self::USER_NAME);

		$this->assertInternalType('array', $s, 'Suspension retrieval did not return an array');
		$this->assertCount(2, $s, 'Incorrect number of suspensions: ' . count($s));

		$this->assertEquals($s[0], Suspension::getSuspension($s[0]->id), 'Should equal individually retrieved suspension');
	}

	/**
	* @depends testCreate
	*/
	public function testFirstSuspension() {
		$s = Suspension::getSuspension(self::$s1);

		$this->assertTrue($s->restricted, 'Suspension should be restricted but is not.');
		$this->assertTrue($s->infinite, 'Suspension should be infinite but is not.');
		$this->assertEquals($s->expires, NULL, 'Expiration date should be NULL');
	}

	/**
	* @depends testCreateForId
	*/
	public function testSecondSuspension() {
		$s = Suspension::getSuspension(self::$s2);

		$this->assertTrue($s->restricted, 'Suspension should be restricted but is not.');
		$this->assertFalse($s->infinite, 'Suspension should be infinite but is not.');
		$this->assertEquals($s->expires, new DateTime(self::EXPIRATION_DATE), 'Expiration date not set properly');
	}

	/**
	* @depends testRetrieveList
	*/
	public function testDelete() {
		foreach (Suspension::getSuspensionsById(self::$id) AS $s) {
			$s->delete();
		}
		$this->assertFalse(Suspension::isSuspendedById(self::$id));
	}
}
?>