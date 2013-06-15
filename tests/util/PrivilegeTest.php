<?php
require_once dirname(__FILE__) . '/../../util/Privilege.php';

class PrivilegeTest extends PHPUnit_Framework_TestCase {
	public function test_toArray() {
		$this->assertEquals(Privilege::toArray(Privilege::NONE), array('none'), 'Failed to convert privilege NONE to array');
		$this->assertEquals(Privilege::toArray(Privilege::NONE|Privilege::REVIEW), array('review'), 'Failed to convert privilege NONE|REVIEW to array');

		$arr = Privilege::toArray(Privilege::ADMIN|Privilege::STDLIB_ADMIN|Privilege::REGISTRATION);
		$this->assertInternalType('array', $arr, 'Privilege conversion did not return an array');
		$this->assertCount(3, $arr, 'Invalid element count in privilege array');
		$this->assertContains('admin', $arr, 'Failed to convert privilege ADMIN|STDLIB_ADMIN|REGISTRATION to array');
		$this->assertContains('stdlib-admin', $arr, 'Failed to convert privilege ADMIN|STDLIB_ADMIN|REGISTRATION to array');
		$this->assertContains('registration', $arr, 'Failed to convert privilege ADMIN|STDLIB_ADMIN|REGISTRATION to array');
	}

	public function test_fromArray() {
		$arr = array('stdlib', 'review');
		$this->assertEquals(Privilege::fromArray($arr), Privilege::STDLIB|Privilege::REVIEW, 'Failed to convert array [stdlib, review] to privilege');

		$arr = array('admin');
		$this->assertEquals(Privilege::fromArray($arr), Privilege::ADMIN, 'Failed to convert array [admin] to privilege');

		$arr = array();
		$this->assertEquals(Privilege::fromArray($arr), Privilege::NONE, 'Failed to convert array [] to privilege');
	}
}
?>