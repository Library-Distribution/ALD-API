<?php
require_once dirname(__FILE__) . '/../../db.php';
require_once dirname(__FILE__) . '/../../SortHelper.php';
require_once dirname(__FILE__) . '/../../FilterHelper.php';
require_once dirname(__FILE__) . '/../../Assert.php';
require_once dirname(__FILE__) . '/../../sql2array.php';
require_once dirname(__FILE__) . '/../../config/stdlib.php';

class Candidate {
	public static function create($item, $user, $reason, $deletion = false) {
		$db_connection = db_ensure_connection();
		$item = $db_connection->real_escape_string($item);
		$user = $db_connection->real_escape_string($user);
		$reason = $db_connection->real_escape_string($reason);
		$deletion = $deletion ? '0' : 'NULL';

		$db_query = 'INSERT INTO ' . DB_TABLE_CANDIDATES . ' (`item`, `user`, `reason`, `approval`) VALUES (UNHEX("' . $item . '"), UNHEX("' . $user . '"), "' . $reason . '", ' . $deletion . ')';
		$db_connection->query($db_query);

		return $db_connection->insert_id;
	}

	public static function describe($id) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);

		$db_query = 'SELECT *, HEX(`item`) AS item, HEX(`user`) AS user FROM ' . DB_TABLE_CANDIDATES . ' WHERE `id` = ' . $id;
		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_result);

		return $db_result->fetch_assoc();
	}

	public static function exists($id) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);

		$db_query = 'SELECT * FROM ' . DB_TABLE_CANDIDATES . ' WHERE `id` = ' . $id;
		$db_result = $db_connection->query($db_query);

		return $db_result->num_rows > 0;
	}

	public static function existsItem($item) {
		$db_connection = db_ensure_connection();
		$item = $db_connection->real_escape_string($item);

		$db_query = 'SELECT * FROM ' . DB_TABLE_CANDIDATES . ' WHERE `item` = UNHEX("' . $item . '")';
		$db_result = $db_connection->query($db_query);

		return $db_result->num_rows > 0;
	}

	public static function accepted($id) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);

		$db_query = 'SELECT COUNT(*) AS count, `final`, `accept` FROM ' . DB_TABLE_CANDIDATE_VOTING . ' WHERE `candidate` = ' . $id . ' GROUP BY `final`, `accept`';
		$db_result = $db_connection->query($db_query);

		$accept = array();
		while ($row = $db_result->fetch_assoc()) {
			if ($row['final'])
				return (bool)$row['accept']; # final decisions overwrite everything else (there must only be one of them)
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
		$item = $db_connection->real_escape_string($item);

		$db_query = 'SELECT `id` FROM ' . DB_TABLE_CANDIDATES . ' WHERE `item` = UNHEX("' . $item . '")';
		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_result);

		$t = $db_result->fetch_assoc();
		return $t['id'];
	}

	public static function approve($id) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);

		$db_query = 'UPDATE ' . DB_TABLE_CANDIDATES . ' SET `approval` = NOW() WHERE `approval` IS NULL AND `id` = ' . $id;
		$db_connection->query($db_query);
		Assert::dbMinRows($db_connection, NULL, 400);
	}

	public static function isApproved($id) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);

		$db_query = 'SELECT (`approval` IS NOT NULL) AS approved FROM ' . DB_TABLE_CANDIDATES . ' WHERE `id` = ' . $id;
		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_result);

		$t = $db_result->fetch_assoc();
		return $t['approved'];
	}

	public static function getUser($id) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);

		$db_query = 'SELECT HEX(`user`) AS user FROM ' . DB_TABLE_CANDIDATES . ' WHERE `id` = ' . $id;
		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_result);

		$t = $db_result->fetch_assoc();
		return $t['user'];
	}

	public static function getItem($id) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);

		$db_query = 'SELECT HEX(`item`) AS item FROM ' . DB_TABLE_CANDIDATES . ' WHERE `id` = ' . $id;
		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_result);

		$t = $db_result->fetch_assoc();
		return $t['item'];
	}

	public static function listCandidates($filters = array(), $sort = array()) {
		if (!is_array($filters)) {
			throw new Exception('Must provide a valid array as candidate filter');
		}
		if (!is_array($sort)) {
			throw new Exception('Must provide a valid array for candidate sorting');
		}
		$db_connection = db_ensure_connection();
		$db_sort = SortHelper::getOrderClause($sort, array('date' => '`date`', 'approval' => '`approval`'));

		$filter = new FilterHelper($db_connection, DB_TABLE_CANDIDATES);

		$filter->add(array('name' => 'item', 'type' => 'binary'));
		$filter->add(array('name' => 'user', 'type' => 'binary'));

		$filter->add(array('name' => 'created', 'db-name' => 'date'));
		$filter->add(array('name' => 'created-before', 'db-name' => 'date', 'operator' => '<'));
		$filter->add(array('name' => 'created-after', 'db-name' => 'date', 'operator' => '>'));

		$filter->add(array('name' => 'approved', 'db-name' => 'approval', 'null' => false));

		$filter->add(array('name' => 'owner', 'db-name' => 'user', 'type' => 'binary', 'db-table' => DB_TABLE_ITEMS, 'join-ref' => 'item', 'join-key' => 'id'));

		$db_cond = $filter->evaluate($filters);
		$db_join = $filter->evaluateJoins();

		$db_query = 'SELECT ' . DB_TABLE_CANDIDATES . '.`id`, HEX(' . DB_TABLE_CANDIDATES. '.`item`) AS item FROM ' . DB_TABLE_CANDIDATES . $db_join . $db_cond . ' ' . $db_sort;
		$db_result = $db_connection->query($db_query);
		return sql2array($db_result);
	}

	public static function listVotings($candidate, $filters = array(), $sort = array()) {
		if (!is_array($filters)) {
			throw new Exception('Must provide a valid array as candidate voting filter');
		}
		if (!is_array($sort)) {
			throw new Exception('Must provide a valid array for candidate voting sorting');
		}
		$db_connection = db_ensure_connection();

		$filter = new FilterHelper($db_connection, DB_TABLE_CANDIDATE_VOTING);
		$filter->add(array('db-name' => 'candidate', 'value' => $candidate, 'type' => 'int'));

		$filter->add(array('name' => 'user', 'type' => 'binary'));
		$filter->add(array('name' => 'final', 'type' => 'switch'));
		$filter->add(array('name' => 'accept', 'type' => 'switch'));

		$filter->add(array('name' => 'voted', 'db-name' => 'date'));
		$filter->add(array('name' => 'voted-before', 'db-name' => 'date', 'operator' => '<'));
		$filter->add(array('name' => 'voted-after', 'db-name' => 'date', 'operator' => '>'));

		$db_cond = $filter->evaluate($filters);
		$db_sort = SortHelper::getOrderClause($sort, array('date' => '`date`'));

		$db_query = 'SELECT `candidate`, HEX(`user`) AS user, `accept`, `final`, `reason`, `date` FROM ' . DB_TABLE_CANDIDATE_VOTING . $db_cond . ' ' . $db_sort;
		$db_result = $db_connection->query($db_query);

		return sql2array($db_result, array('Candidate', '_cleanup_voting'));
	}
	static function _cleanup_voting($item, $key) {
		$item['accept'] = (bool)$item['accept'];
		$item['final'] = (bool)$item['final'];
		return $item;
	}

	public static function vote($candidate, $user, $accept, $reason, $final = false) {
		$db_connection = db_ensure_connection();

		$candidate = $db_connection->real_escape_string($candidate);
		$user = $db_connection->real_escape_string($user);
		$reason = $db_connection->real_escape_string($reason);
		$accept = $accept ? 'TRUE' : 'FALSE';
		$final = $final ? 'TRUE' : 'FALSE';

		$db_query = 'INSERT INTO ' . DB_TABLE_CANDIDATE_VOTING . ' (`candidate`, `user`, `accept`, `final`, `reason`) VALUES (' . $candidate . ', UNHEX("' . $user . '"), ' . $accept . ', ' . $final . ', "' . $reason . '")';
		$db_connection->query($db_query);
	}

	public static function hasVoted($id, $user) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);
		$user = $db_connection->real_escape_string($user);

		$db_query = 'SELECT * FROM ' . DB_TABLE_CANDIDATE_VOTING . ' WHERE `user` = UNHEX("' . $user . '") AND `candidate` = ' . $id;
		$db_result = $db_connection->query($db_query);

		return $db_result->num_rows > 0;
	}
}
?>