<?php
require_once 'PHPUnit/Autoload.php';
require_once 'PHPUnit/Framework/Assert/Functions.php'; # should not be required (?)

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

	public static function testBeforeSuspension() {
		assertFalse(Suspension::isSuspended(self::USER_NAME));
		assertFalse(Suspension::isSuspendedById(self::$id));
	}

	/**
	* @depends testBeforeSuspension
	*/
	public static function testCreate() {
		self::$s1 = Suspension::create(self::USER_NAME, 'Suspended for no particular reason');
		assertInternalType('int', self::$s1, 'Suspension creation did not return an integer');
		assertTrue(Suspension::isSuspended(self::USER_NAME));
	}

	/**
	* @depends testBeforeSuspension
	*/
	public static function testCreateForId() {
		self::$s2 = Suspension::createForId(self::$id, 'For testing reasons', self::EXPIRATION_DATE);
		assertInternalType('int', self::$s2, 'Suspension creation (for ID) did not return an integer');
		assertTrue(Suspension::isSuspendedById(self::$id));
	}

	/**
	* @depends testCreate
	* @depends testCreateForId
	*/
	public static function testRetrieveList() {
		$s = Suspension::getSuspensions(self::USER_NAME);

		assertInternalType('array', $s, 'Suspension retrieval did not return an array');
		assertCount(2, $s, 'Incorrect number of suspensions: ' . count($s));

		assertEquals($s[0], Suspension::getSuspension($s[0]->id), 'Should equal individually retrieved suspension');
	}

	/**
	* @depends testCreate
	*/
	public static function testFirstSuspension() {
		$s = Suspension::getSuspension(self::$s1);

		assertTrue($s->restricted, 'Suspension should be restricted but is not.');
		assertTrue($s->infinite, 'Suspension should be infinite but is not.');
		assertEquals($s->expires, NULL, 'Expiration date should be NULL');
	}

	/**
	* @depends testCreateForId
	*/
	public static function testSecondSuspension() {
		$s = Suspension::getSuspension(self::$s2);

		assertTrue($s->restricted, 'Suspension should be restricted but is not.');
		assertFalse($s->infinite, 'Suspension should be infinite but is not.');
		assertEquals($s->expires, new DateTime(self::EXPIRATION_DATE), 'Expiration date not set properly');
	}

	/**
	* @depends testRetrieveList
	*/
	public static function testDelete() {
		foreach (Suspension::getSuspensionsById(self::$id) AS $s) {
			$s->delete();
		}
		assertFalse(Suspension::isSuspendedById(self::$id));
	}
}
?>