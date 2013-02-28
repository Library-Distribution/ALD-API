<?php
require_once(dirname(__FILE__) . '/../../db.php');
require_once(dirname(__FILE__) . '/../../modules/HttpException/HttpException.php');

class Candidate {
	public static function create($item, $user, $reason) {
		$db_connection = db_ensure_connection();
		$item = mysql_real_escape_string($item, $db_connection);
		$user = mysql_real_escape_string($user, $db_connection);
		$reason = mysql_real_escape_string($reason, $db_connection);

		$db_query = 'INSERT INTO ' . DB_TABLE_CANDIDATE . ' (`item`, `user`, `reason`) VALUES (UNHEX("' . $item . '"), UNHEX("' . $user . '"), "' . $reason . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return mysql_insert_id($db_connection);
	}

	public static function exists($item) {
		$db_connection = db_ensure_connection();
		$item = mysql_real_escape_string($item, $db_connection);

		$db_query = 'SELECT * FROM ' . DB_TABLE_CANDIDATE . ' WHERE `item` = UNHEX("' . $item . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return mysql_num_rows($db_result) > 0;
	}

	public static function accepted($item) {
		# ... todo ...
	}
}
?>