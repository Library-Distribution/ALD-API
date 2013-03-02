<?php
require_once(dirname(__FILE__) . '/../../db.php');
require_once(dirname(__FILE__) . '/../../sql2array.php');
require_once(dirname(__FILE__) . '/../../config/stdlib.php');
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

	public static function describe($id) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT *, HEX(`item`) AS item, HEX(`user`) AS user FROM ' . DB_TABLE_CANDIDATES . ' WHERE `id` = ' . $id;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
		if (mysql_num_rows($db_result) < 1) {
			throw new HttpException(404);
		}

		return mysql_fetch_assoc($db_result);
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

	public static function accepted($id) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT COUNT(*) AS count, `final`, `accept` FROM ' . DB_TABLE_CANDIDATE_VOTING . ' WHERE `candidate` = ' . $id . ' GROUP BY `final`, `accept`';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		$accept = array();
		while ($row = mysql_fetch_assoc($db_result)) {
			if ($row['final'])
				return $row['accept']; # final decisions overwrite everything else (there must only be one of them)
			$accept[$row['accept']] = $row['count'];
		}
		if (CANDIDATE_ALWAYS_REQUIRE_FINAL)
			return NULL; # if there had been a final, it would already have exited the loop

		if ($accept[true] >= CANDIDATE_MIN_ACCEPTS && $accept[false] == 0 && !CANDIDATE_ACCEPT_REQUIRE_FINAL)
			return true; # accepted based on the current (non-final) accepts
		else if ($accept[false] >= CANDIDATE_MIN_REJECTS && $accept[true] == 0 && !CANDIDATE_REJECT_REQUIRE_FINAL)
			return false; # rejected based on the current (no-final) rejects

		return NULL; # must still be open
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

	public static function isApproved($id) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT (`approval` IS NOT NULL) AS approved FROM ' . DB_TABLE_CANDIDATES . ' WHERE `id` = ' . $id;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
		if (mysql_num_rows($db_result) < 1) {
			throw new HttpException(404);
		}

		$t = mysql_fetch_assoc($db_result);
		return $t['approved'];
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

	public static function listCandidates($filters = array()) {
		if (!is_array($filters)) {
			throw new Exception('Must provide a valid array as candidate filter');
		}
		$db_connection = db_ensure_connection();
		$db_cond = '';

		foreach (array('item', 'user') AS $field) { # filter binary fields
			if (isset($filters[$field])) {
				$db_cond .= ($db_cond ? ' AND ' : ' WHERE ') . '`' . $field . '` = UNHEX("' . mysql_real_escape_string($filters[$field], $db_connection) . '")';
			}
		}

		foreach (array('created' => '=', 'created-after' => '>', 'created-before' => '<') AS $field => $op) { # filter creation date
			if (isset($filters[$field])) {
				$db_cond .= ($db_cond ? ' AND ' : ' WHERE ') . '`date` ' . $op . ' "' . mysql_real_escape_string($filters[$field], $db_connection) . '"';
			}
		}

		if (isset($filters['approved'])) {
			if (in_array($filters['approved'], array(1, '+1', 'yes', 'true'))) {
				$db_cond .= ($db_cond ? ' AND ' : ' WHERE ') . '`approval` IS NOT NULL';
			} else if (in_array($filters['approved'], array(-1, 'no', 'false'))) {
				$db_cond .= ($db_cond ? ' AND ' : ' WHERE ') . '`approval` IS NULL';
			}
			# else if (in_array($filters['approved'], array(0, 'both'))) - the default
		}

		$db_query = 'SELECT `id`, HEX(`item`) AS item FROM ' . DB_TABLE_CANDIDATES . $db_cond;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return sql2array($db_result);
	}

	public static function listVotings($candidate) {
		$db_connection = db_ensure_connection();
		$candidate = (int)mysql_real_escape_string($candidate, $db_connection);

		$db_query = 'SELECT `candidate`, HEX(`user`) AS user, `accept`, `final`, `reason`, `date` FROM ' . DB_TABLE_CANDIDATE_VOTING . ' WHERE `candidate` = ' . $candidate;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return sql2array($db_result);
	}

	public static function vote($candidate, $user, $accept, $reason, $final = false) {
		$db_connection = db_ensure_connection();

		$candidate = mysql_real_escape_string($candidate, $db_connection);
		$user = mysql_real_escape_string($user, $db_connection);
		$reason = mysql_real_escape_string($reason, $db_connection);
		$accept = $accept ? 'TRUE' : 'FALSE';
		$final = $final ? 'TRUE' : 'FALSE';

		$db_query = 'INSERT INTO ' . DB_TABLE_CANDIDATE_VOTING . ' (`candidate`, `user`, `accept`, `final`, `reason`) VALUES (' . $candidate . ', UNHEX("' . $user . '"), ' . $accept . ', ' . $final . ', "' . $reason . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}

	public static function hasVoted($id, $user) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);
		$user = mysql_real_escape_string($user, $db_connection);

		$db_query = 'SELECT * FROM ' . DB_TABLE_CANDIDATE_VOTING . ' WHERE `user` = UNHEX("' . $user . '") AND `candidate` = ' . $id;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return mysql_num_rows($db_result) > 0;
	}
}
?>