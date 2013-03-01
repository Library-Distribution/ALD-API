<?php
require_once(dirname(__FILE__) . '/../../db.php');
require_once(dirname(__FILE__) . '/../../sql2array.php');
require_once(dirname(__FILE__) . '/../../modules/HttpException/HttpException.php');

class Candidate {
	public static function create($item, $user, $reason) {
		$db_connection = db_ensure_connection();
		$item = mysql_real_escape_string($item, $db_connection);
		$user = mysql_real_escape_string($user, $db_connection);
		$reason = mysql_real_escape_string($reason, $db_connection);

		$db_query = 'INSERT INTO ' . DB_TABLE_CANDIDATES . ' (`item`, `user`, `reason`) VALUES (UNHEX("' . $item . '"), UNHEX("' . $user . '"), "' . $reason . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return mysql_insert_id($db_connection);
	}

	public static function exists($id) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT * FROM ' . DB_TABLE_CANDIDATES . ' WHERE `id` = ' . $id;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return mysql_num_rows($db_result) > 0;
	}

	public static function existsItem($item) {
		$db_connection = db_ensure_connection();
		$item = mysql_real_escape_string($item, $db_connection);

		$db_query = 'SELECT * FROM ' . DB_TABLE_CANDIDATES . ' WHERE `item` = UNHEX("' . $item . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return mysql_num_rows($db_result) > 0;
	}

	public static function accepted($item) {
		# ... todo ...
	}

	public static function getId($item) {
		$db_connection = db_ensure_connection();
		$item = mysql_real_escape_string($item, $db_connection);

		$db_query = 'SELECT `id` FROM ' . DB_TABLE_CANDIDATES . ' WHERE `item` = UNHEX("' . $item . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
		if (mysql_num_rows($db_result) < 1) {
			throw new HttpException(404);
		}

		$t = mysql_fetch_assoc($db_result);
		return $t['id'];
	}

	public static function approve($id) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);

		$db_query = 'UPDATE ' . DB_TABLE_CANDIDATES . ' SET `approval` = NOW() WHERE `id` = ' . $id;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}

	public static function getUser($id) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT HEX(`user`) AS user FROM ' . DB_TABLE_CANDIDATES . ' WHERE `id` = ' . $id;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
		if (mysql_num_rows($db_result) < 1) {
			throw new HttpException(404);
		}

		$t = mysql_fetch_assoc($db_result);
		return $t['user'];
	}

	public static function getItem($id) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT HEX(`item`) AS item FROM ' . DB_TABLE_CANDIDATES . ' WHERE `id` = ' . $id;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
		if (mysql_num_rows($db_result) < 1) {
			throw new HttpException(404);
		}

		$t = mysql_fetch_assoc($db_result);
		return $t['item'];
	}

	public static function listCandidates() {
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT `id`, HEX(`item`) AS item FROM ' . DB_TABLE_CANDIDATES; # todo: include status + approval
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return sql2array($db_result);
	}

	public static function rate($candidate, $user, $accept, $reason, $final = false) {
		$db_connection = db_ensure_connection();

		$candidate = mysql_real_escape_string($candidate, $db_connection);
		$user = mysql_real_escape_string($user, $db_connection);
		$reason = mysql_real_escape_string($reason, $db_connection);
		$accept = $accept ? 'TRUE' : 'FALSE';
		$final = $final ? 'TRUE' : 'FALSE';

		$db_query = 'INSERT INTO ' . DB_TABLE_CANDIDATE_RATING . ' (`candidate`, `user`, `accept`, `final`, `reason`) VALUES (' . $candidate . ', UNHEX("' . $user . '"), ' . $accept . ', ' . $final . ', "' . $reason . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}
}
?>