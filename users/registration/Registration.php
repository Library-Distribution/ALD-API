<?php
require_once(dirname(__FILE__) . '/../../db.php');
require_once(dirname(__FILE__) . '/../../util.php');
require_once(dirname(__FILE__) . '/../../User.php');
require_once(dirname(__FILE__) . '/../../config/registration.php');
require_once(dirname(__FILE__) . '/../../modules/HttpException/HttpException.php');

class Registration {
	public static function create($name, $mail, $password) {
		self::validateName($name);

		$db_connection = db_ensure_connection();

		$name = mysql_real_escape_string($name, $db_connection);
		$mail = mysql_real_escape_string($mail, $db_connection);
		$password = mysql_real_escape_string($password, $db_connection);

		$id = mt_rand();
		$token = self::createToken();

		$db_query = 'INSERT INTO ' . DB_TABLE_REGISTRATION . ' (`id`, `token`, `name`, `mail`, `password`) VALUES ("' . $id . '", "' . $token . '", "' . $name . '", "' . $mail . '", "' . $password . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return $id;
	}

	public static function clear() {
		$db_connection = db_ensure_connection();

		$db_query = 'DELETE FROM ' . DB_TABLE_REGISTRATION . ' WHERE `created` + INTERVAL ' . REGISTRATION_TIMEOUT . ' <= NOW()';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}

	public static function existsPending($name, $mail) {
		$db_connection = db_ensure_connection();
		$name = mysql_real_escape_string($name, $db_connection);
		$mail = mysql_real_escape_string($mail, $db_connection);

		$db_query = 'SELECT * FROM ' . DB_TABLE_REGISTRATION . ' WHERE `name` = "' . $name . '" OR `mail` = "' . $mail . '"';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
		return mysql_num_rows($db_result) > 0;
	}

	public static function get($id) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT * FROM ' . DB_TABLE_REGISTRATION . ' WHERE `id` = ' . $id;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		if (mysql_num_rows($db_result) < 1) {
			throw new HttpException(404);
		}

		return mysql_fetch_assoc($db_result);
	}

	public static function delete($id) {
		$db_connection = db_ensure_connection();
		$id = (int)mysql_real_escape_string($id, $db_connection);

		$db_query = 'DELETE FROM ' . DB_TABLE_REGISTRATION . ' WHERE `id` = ' . $id;
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}

	private static function createToken() {
		$chars = str_split('ABCDEFGHKLMNPQRSTWXYZ23456789');
		shuffle($chars);
		return implode(array_slice($chars, 0, 10));
	}

	private static function validateName($name) {
		if (!preg_match( USER_NAME_REGEX, $name)) {
			throw new HttpException(403, NULL, 'Invalid user name');
		}

		$forbidden = explode("\0", FORBIDDEN_USER_NAMES);
		if (in_array($name, $forbidden)) {
			throw new HttpException(403, NULL, 'Forbidden user name');
		}

		$reserved = explode("\0", RESERVED_USER_NAMES);
		if (in_array($name, $reserved)) 	{
			user_basic_auth('Trying to register a reserved user name');
			if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], User::PRIVILEGE_REGISTRATION)) {
				throw new HttpException(403, NULL, 'Trying to register a reserved user name');
			}
		}
	}
}
?>