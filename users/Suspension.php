<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../User.php');
require_once(dirname(__FILE__) . '/../sql2array.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');
require_once(dirname(__FILE__) . '/../config/suspensions.php');

class Suspension {
	public static function create($user, $length = NULL, $restricted = true) {
		self::createForId(User::getID($user), $length, $restricted);
	}

	public static function createForId($user, $length = NULL, $restricted = true) {
		$db_connection = db_ensure_connection();

		$user = mysql_real_escape_string($user, $db_connection);
		if ($length !== NULL) {
			$length = (int)mysql_real_escape_string($length, $db_connection);
		}
		$restricted = $restricted ? '1' : '0';

		$db_query = 'INSERT INTO ' . DB_TABLE_SUSPENSIONS . ' (`user`, `length`, `restricted`) VALUES (UNHEX("' . $user . '"), ' . ($length !== NULL ? '"' . $length . '"' : 'NULL') . ', ' . $restricted . ')';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}

	public static function clear() {
		$db_connection = db_ensure_connection();

		$cond = ' `since` + INTERVAL `length` ' . SUSPENSION_INTERVAL_UNIT . ' <= NOW() AND `length` != NULL';
		if (CLEAR_SUSPENSIONS) {
			$db_query = 'DELETE FROM ' . DB_TABLE_SUSPENSIONS . ' WHERE' . $cond;
		} else {
			$db_query = 'UPDATE ' . DB_TABLE_SUSPENSIONS . ' SET `cleared` = TRUE WHERE' . $cond;
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

	public static function getSuspensions($user, $cleared = false) {
		return self::getSuspensionsById(User::getID($user), $cleared);
	}

	public static function getSuspensionsById($id, $cleared = false) {
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT *, HEX(user) AS user, (`length` IS NULL) AS infinite, (`since` + INTERVAL `length` ' . SUSPENSION_INTERVAL_UNIT . ') AS expires FROM ' . DB_TABLE_SUSPENSIONS . ' WHERE `user` = UNHEX("' . $id . '")'
					. ($cleared === NULL ? '' : ($cleared
						? ' HAVING `cleared` OR (NOT infinite AND expires <= NOW())'
						: ' HAVING NOT `cleared` AND (infinite OR expires > NOW())'));
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return sql2array($db_result, array('Suspension', '_create_inst_'));
	}

	public static function _create_inst_($arr) {
		return new Suspension((int)$arr['id'], $arr['user'], $arr['since'], (int)$arr['length'], (bool)$arr['infinite'] ? NULL : $arr['expires'], (bool)$arr['restricted']);
	}

	####################################

	private function __construct($id, $user, $since, $length, $expires, $restricted) {
		$this->id = $id;
		$this->user = $user;
		$this->restricted = $restricted;

		$this->since = new DateTime($since);
		$this->length = DateInterval::createFromDateString($length . SUSPENSION_INTERVAL_UNIT);
		$this->expires = ($this->infinite = $expires === NULL) ? NULL : new DateTime($expires);
	}

	public function delete() {
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($this->id, $db_connection);

		$db_query = 'DELETE FROM ' . DB_TABLE_SUSPENSIONS . ' WHERE `id` = "' . $id . '"';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}

	public $id;
	public $user;
	public $since;
	public $length;
	public $expires;
	public $infinite;
	public $restricted;
}
?>