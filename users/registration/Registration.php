<?php
require_once(dirname(__FILE__) . '/../../db.php');
require_once(dirname(__FILE__) . '/../../util.php');
require_once(dirname(__FILE__) . '/../../User.php');
require_once dirname(__FILE__) . '/../../util/Privilege.php';
require_once(dirname(__FILE__) . '/../../Assert.php');
require_once(dirname(__FILE__) . '/../../config/registration.php');
require_once(dirname(__FILE__) . '/../../modules/HttpException/HttpException.php');

class Registration {
	public static function create($name, $mail, $password) {
		self::validateName($name);

		$db_connection = db_ensure_connection();

		$name = $db_connection->real_escape_string($name);
		$mail = $db_connection->real_escape_string($mail);
		$password = $db_connection->real_escape_string($password);

		$id = mt_rand();
		$token = self::createToken();

		$db_query = 'INSERT INTO ' . DB_TABLE_REGISTRATION . ' (`id`, `token`, `name`, `mail`, `password`) VALUES ("' . $id . '", "' . $token . '", "' . $name . '", "' . $mail . '", "' . $password . '")';
		$db_connection->query($db_query);

		return $id;
	}

	public static function clear() {
		$db_connection = db_ensure_connection();

		$db_query = 'DELETE FROM ' . DB_TABLE_REGISTRATION . ' WHERE `created` + INTERVAL ' . REGISTRATION_TIMEOUT . ' <= NOW()';
		$db_connection->query($db_query);
	}

	public static function existsPending($name, $mail) {
		$db_connection = db_ensure_connection();
		$name = $db_connection->real_escape_string($name);
		$mail = $db_connection->real_escape_string($mail);

		$db_query = 'SELECT * FROM ' . DB_TABLE_REGISTRATION . ' WHERE `name` = "' . $name . '" OR `mail` = "' . $mail . '"';
		$db_result = $db_connection->query($db_query);
		return $db_result->num_rows > 0;
	}

	public static function get($id) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);

		$db_query = 'SELECT * FROM ' . DB_TABLE_REGISTRATION . ' WHERE `id` = ' . $id;
		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_result);

		return $db_result->fetch_assoc();
	}

	public static function delete($id) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);

		$db_query = 'DELETE FROM ' . DB_TABLE_REGISTRATION . ' WHERE `id` = ' . $id;
		$db_connection->query($db_query);
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
			if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::REGISTRATION_ADMIN)) {
				throw new HttpException(403, NULL, 'Trying to register a reserved user name');
			}
		}
	}
}
?>