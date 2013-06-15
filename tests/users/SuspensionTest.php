<?php
require_once dirname(__FILE__) . '/../ALD_Database_TestCase.php';

require_once dirname(__FILE__) . '/../../config/database.php';
require_once dirname(__FILE__) . '/../../User.php';
require_once dirname(__FILE__) . '/../../users/Suspension.php';

class SuspensionTest extends ALD_Database_TestCase {

	const USER1_NAME = 'Frank';
	const USER1_ID = '16A143CE32B14162ABA8C08EA962B42A';

	const USER2_NAME = 'Ben';
	const USER2_ID = '281B3C505B5A4CE59DA856E39C9470F5';

	public function test_isSuspended() {
		$this->assertFalse(Suspension::isSuspended(self::USER1_NAME));
		$this->assertTrue(Suspension::isSuspended(self::USER2_NAME));
	}

	public function test_isSuspendedById() {
		$this->assertFalse(Suspension::isSuspendedById(self::USER1_ID));
		$this->assertTrue(Suspension::isSuspendedById(self::USER2_ID));
	}

	/**
	* @depends test_isSuspended
	*/
	public function test_create() {
		$count_before = $this->getConnection()->getRowCount(DB_TABLE_SUSPENSIONS);

		$s = Suspension::create(self::USER1_NAME, 'Suspended for no particular reason');
		$this->assertInternalType('int', $s, 'Suspension creation did not return an integer');

		$this->assertEquals($count_before + 1, $this->getConnection()->getRowCount(DB_TABLE_SUSPENSIONS));
		$this->assertTrue(Suspension::isSuspended(self::USER1_NAME));
	}

	/**
	* @depends test_isSuspendedById
	*/
	public function test_createForId() {
		$count_before = $this->getConnection()->getRowCount(DB_TABLE_SUSPENSIONS);

		$s = Suspension::createForId(self::USER1_ID, 'For testing reasons', '2037-04-15 00:03:49');
		$this->assertInternalType('int', $s, 'Suspension creation (for ID) did not return an integer');

		$this->assertEquals($count_before + 1, $this->getConnection()->getRowCount(DB_TABLE_SUSPENSIONS));
		$this->assertTrue(Suspension::isSuspendedById(self::USER1_ID));
	}

	public function test_getSuspensions() {
		$s = Suspension::getSuspensions(self::USER2_NAME);

		$this->assertInternalType('array', $s, 'Suspension retrieval did not return an array');
		$this->assertCount(2, $s, 'Incorrect number of suspensions: ' . count($s));
	}

	public function test_getSuspensionsById() {
		$s = Suspension::getSuspensionsById(self::USER2_ID);

		$this->assertInternalType('array', $s, 'Suspension retrieval did not return an array');
		$this->assertCount(2, $s, 'Incorrect number of suspensions: ' . count($s));
	}

	/**
	* @depends test_getSuspensionsById
	* @depends test_isSuspendedById
	*/
	public function test_delete() {
		foreach (Suspension::getSuspensionsById(self::USER2_ID) AS $s) {
			$s->delete();
		}

		foreach (Suspension::getSuspensionsById(self::USER2_ID) AS $s) {
			$this->assertFalse($s->active);
		}
		$this->assertFalse(Suspension::isSuspendedById(self::USER2_ID));
	}

	/**
	* @depends test_getSuspensionsById
	*/
	public function test__suspensions_valid() {
		$s = Suspension::getSuspensionsById(self::USER2_ID);

		$this->assertTrue($s[0]->restricted, 'Suspension should be restricted but is not.');
		$this->assertTrue($s[0]->infinite, 'Suspension should be infinite but is not.');
		$this->assertEquals($s[0]->expires, NULL, 'Expiration date should be NULL');

		$this->assertFalse($s[1]->restricted, 'Suspension should not be restricted but is.');
		$this->assertFalse($s[1]->infinite, 'Suspension should not be infinite but is.');
		$this->assertEquals($s[1]->expires, new DateTime('2033-08-05 12:00:05'), 'Expiration date not set properly');
	}
}
?>