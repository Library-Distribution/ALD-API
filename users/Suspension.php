<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../User.php');
require_once(dirname(__FILE__) . '/../sql2array.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');
require_once(dirname(__FILE__) . '/../config/suspensions.php');

class Suspension {
	public static function create($user, $reason, $expires = NULL, $restricted = true) {
		return self::createForId(User::getID($user), $reason, $expires, $restricted);
	}

	public static function createForId($user, $reason, $expires = NULL, $restricted = true) {
		$db_connection = db_ensure_connection();

		$user = mysql_real_escape_string($user, $db_connection);
		if ($expires !== NULL) {
			$expires = mysql_real_escape_string($expires, $db_connection);
		}
		$restricted = $restricted ? '1' : '0';
		$reason = mysql_real_escape_string($reason, $db_connection);

		$db_query = 'INSERT INTO ' . DB_TABLE_SUSPENSIONS . ' (`user`, `expires`, `restricted`, `reason`) VALUES (UNHEX("' . $user . '"), ' . ($expires !== NULL ? '"' . $expires . '"' : 'NULL') . ', ' . $restricted . ', "' . $reason . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE || mysql_affected_rows() < 1) {
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
		return self::isSuspendedById(User::getID($user));
	}

	public static function isSuspendedById($id) {
		return count(self::getSuspensionsById($id)) != 0;
	}

	public static function getSuspensions($user, $active = true) {
		return self::getSuspensionsById(User::getID($user), $active);
	}

	public static function getSuspensionsById($id, $active = true) {
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT *, HEX(`user`) AS user FROM ' . DB_TABLE_SUSPENSIONS . ' WHERE `user` = UNHEX("' . $id . '")'
					. ($active === NULL ? '' : ($active
						? ' AND (`active` AND (`expires` IS NULL OR `expires` > NOW()))'
						: ' AND (NOT `active` OR (`expires` IS NOT NULL AND `expires` <= NOW()))'));
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