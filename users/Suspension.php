<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../User.php');
require_once(dirname(__FILE__) . '/../SortHelper.php');
require_once(dirname(__FILE__) . '/../sql2array.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');
require_once(dirname(__FILE__) . '/../config/suspensions.php');

class Suspension {
	public static function create($user, $reason, $expires = NULL, $restricted = true) {
		self::createForId(User::getID($user), $reason, $expires, $restricted);
	}

	public static function createForId($user, $reason, $expires = NULL, $restricted = true) {
		$db_connection = db_ensure_connection();

		$user = mysql_real_escape_string($user, $db_connection);
		if ($expires !== NULL) {
			$expires = (int)mysql_real_escape_string($expires, $db_connection);
		}
		$restricted = $restricted ? '1' : '0';
		$reason = mysql_real_escape_string($reason, $db_connection);

		$db_query = 'INSERT INTO ' . DB_TABLE_SUSPENSIONS . ' (`user`, `expires`, `restricted`, `reason`) VALUES (UNHEX("' . $user . '"), ' . ($expires !== NULL ? '"' . $expires . '"' : 'NULL') . ', ' . $restricted . ', "' . $reason . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return mysql_insert_id($db_connection);
	}

	public static function clear() {
		$db_connection = db_ensure_connection();

		$cond = ' `expires` IS NOT NULL AND `expires` <= NOW()';
		if (CLEAR_SUSPENSIONS) {
			$db_query = 'DELETE FROM ' . DB_TABLE_SUSPENSIONS . ' WHERE' . $cond;
		} else {
			$db_query = 'UPDATE ' . DB_TABLE_SUSPENSIONS . ' SET `active` = FALSE WHERE `active` AND' . $cond;
		}

		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}

	public static function isSuspended($user) {
		return self::getSuspensions($user);
	}

	public static function isSuspendedById($id) {
		return count(self::getSuspensionsById($id)) != 0;
	}

	public static function getSuspensions($user, $filters = array(), $sort = array()) {
		return self::getSuspensionsById(User::getID($user), $filters, $sort);
	}

	public static function getSuspensionsById($id, $filters = array(), $sort = array()) {
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);

		if (!is_array($filters)) {
			throw new HttpException(500, NULL, 'Must pass a valid array as suspension filter!');
		}

		$db_cond = ' AND (`active` AND (`expires` IS NULL OR `expires` > NOW()))'; # if no 'active' filter is specified, assume active = true
		if (isset($filters['active'])) {
			if (in_array($filters['active'], array('no', 'false', -1))) {
				$db_cond = ' AND (NOT `active` OR (`expires` IS NOT NULL AND `expires` <= NOW()))';
			} else if (in_array($filters['active'], array('both', 0))) {
				$db_cond = ''; # empty to override the default
			}
		}

		foreach (array('expires' => '`expires`', 'created' => '`created`') AS $property => $db_field) { # filter by creation and expiration date
			foreach (array($property => '=', $property . '-after' => '>', $property . '-before' => '<') AS $field => $op) {
				if (isset($filters[$field])) {
					$db_cond .= ' AND ' . $db_field . ' ' . $op . ' "' . mysql_real_escape_string($filters[$field], $db_connection) . '"';
				}
			}
		}

		if (isset($filters['infinite'])) {
			if (in_array($filters['infinite'], array('yes', 'true', '+1', 1))) {
				$db_cond .= ' AND `expires` IS NULL';
			} else if (in_array($filters['infinite'], array('no', 'false', -1))) {
				$db_cond .= ' AND `expires` IS NOT NULL';
			}
			# default: both, 0
		}

		if (isset($filters['restricted'])) {
			if (in_array($filters['restricted'], array('yes', 'true', '+1', 1))) {
				$db_cond .= ' AND `restricted`';
			} else if (in_array($filters['restricted'], array('no', 'false', -1))) {
				$db_cond .= ' AND NOT `restricted`';
			}
			# default: both, 0
		}

		$sort = SortHelper::getOrderClause($sort, array('created' => '`created`', 'expires' => '`expires`'));

		$db_query = 'SELECT *, HEX(`user`) AS user FROM ' . DB_TABLE_SUSPENSIONS . ' WHERE `user` = UNHEX("' . $id . '")'
					. $db_cond
					. $sort;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return sql2array($db_result, array('Suspension', '_create_inst_'));
	}

	public static function getSuspension($id) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT *, HEX(`user`) AS user FROM ' . DB_TABLE_SUSPENSIONS . ' WHERE `id` =' . $id;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		if (mysql_num_rows($db_result) != 1) {
			throw new HttpException(404);
		}

		return self::_create_inst_(mysql_fetch_assoc($db_result));
	}

	public static function _create_inst_($arr) {
		return new Suspension((int)$arr['id'], $arr['user'], $arr['created'], $arr['expires'], (bool)$arr['restricted'], $arr['reason'], $arr['active']);
	}

	####################################

	private function __construct($id, $user, $created, $expires, $restricted, $reason, $active) {
		$this->id = $id;
		$this->user = $user;
		$this->restricted = $restricted;
		$this->reason = $reason;

		$this->created = new DateTime($created);
		$this->expires = ($this->infinite = $expires === NULL) ? NULL : new DateTime($expires);

		!$this->infinite AND $diff = $this->expires->diff(new DateTime('now'));
		$this->active = $active && ($this->infinite || $diff->invert);
	}

	public function delete() {
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($this->id, $db_connection);

		$db_query = 'UPDATE ' . DB_TABLE_SUSPENSIONS . ' SET `active` = FALSE WHERE `id` = "' . $id . '"';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}

	public $id;
	public $user;
	public $created;
	public $expires;
	public $infinite;
	public $restricted;
	public $reason;
	public $active;
}
?>