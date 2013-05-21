<?php
require_once(dirname(__FILE__) . '/../config/database.php');
require_once('PHPUnit/Framework/TestCase.php');

abstract class ALD_Database_TestCase extends PHPUnit_Extensions_Database_TestCase {
	static $conn = NULL;

	public function getConnection() {
		if (self::$conn === NULL) {
			$db = new PDO('mysql:dbname=' . DB_NAME . ';host=127.0.0.1', DB_USERNAME, DB_PASSWORD);
			self::$conn = $this->createDefaultDBConnection($db, DB_NAME);
		}
		return self::$conn;
	}
}