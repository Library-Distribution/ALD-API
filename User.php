<?php
require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . '/Assert.php');
require_once(dirname(__FILE__) . '/users/Suspension.php');
require_once(dirname(__FILE__) . "/modules/HttpException/HttpException.php");

class User
{
	const PRIVILEGE_NONE = 0;

	const PRIVILEGE_MODERATOR = 2;
	const PRIVILEGE_MODERATOR_ADMIN = 4;

	const PRIVILEGE_REVIEW = 8;
	const PRIVILEGE_REVIEW_ADMIN = 16;

	const PRIVILEGE_STDLIB = 32;
	const PRIVILEGE_STDLIB_ADMIN = 64;

	const PRIVILEGE_REGISTRATION = 128;
	const PRIVILEGE_REGISTRATION_ADMIN = 256;

	const PRIVILEGE_ADMIN = 512;

	private static $privilege_map = array('none' => self::PRIVILEGE_NONE, 'admin' => self::PRIVILEGE_ADMIN,
							'user-mod' => self::PRIVILEGE_MODERATOR, 'user-mod-admin' => self::PRIVILEGE_MODERATOR_ADMIN,
							'review' => self::PRIVILEGE_REVIEW, 'review-admin' => self::PRIVILEGE_REVIEW_ADMIN,
							'stdlib' => self::PRIVILEGE_STDLIB, 'stdlib-admin' => self::PRIVILEGE_STDLIB_ADMIN,
							'registration' => self::PRIVILEGE_REGISTRATION, 'registration-admin' => self::PRIVILEGE_REGISTRATION_ADMIN);

	public static function privilegeToArray($privilege) {
		$arr = array();

		foreach (self::$privilege_map AS $str => $priv) {
			if ($priv !== self::PRIVILEGE_NONE) {
				if (($privilege & $priv) == $priv) {
					$arr[] = $str;
				}
			} else if ($privilege == $priv) {
				$arr[] = 'none';
			}
		}

		return $arr;
	}

	public static function privilegeFromArray($arr) {
		$privilege = self::PRIVILEGE_NONE;

		foreach ($arr AS $priv) {
			if (array_key_exists($priv, self::$privilege_map)) {
				if ($priv != 'none') {
					$privilege |= self::$privilege_map[$priv];
				} else if (count($arr) > 1) {
					throw new HttpException(500);
				}
			} else {
				throw new HttpException(500);
			}
		}

		return $privilege;
	}

	public static function hasPrivilegeById($id, $privilege)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT privileges FROM " . DB_TABLE_USERS . " WHERE id = UNHEX('" . $db_connection->real_escape_string($id) . "')";
		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_result, 'User not found');

		$data = $db_result->fetch_assoc();
		return (((int)$data['privileges']) & $privilege) == $privilege;
	}

	public static function hasPrivilege($name, $privilege)
	{
		return self::hasPrivilegeById(self::getID($name), $privilege);
	}

	public static function existsName($name)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT id FROM " . DB_TABLE_USERS . " WHERE name = '" . $db_connection->real_escape_string($name) . "'";
		$db_result = $db_connection->query($db_query);

		return $db_result->num_rows == 1;
	}

	public static function existsMail($mail)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT id FROM " . DB_TABLE_USERS . " WHERE mail = '" . $db_connection->real_escape_string($mail) . "'";
		$db_result = $db_connection->query($db_query);

		return $db_result->num_rows == 1;
	}

	public static function validateLogin($user, $pw)
	{
		$db_connection = db_ensure_connection();

		$pw = hash("sha256", $pw);
		$escaped_user = $db_connection->real_escape_string($user);

		$db_query = "SELECT pw FROM " . DB_TABLE_USERS . " WHERE name = '$escaped_user'";
		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_result, 'User not found', 403);

		$data = $db_result->fetch_assoc();
		if ($data['pw'] != $pw)
		{
			throw new HttpException(403, NULL, "Invalid credentials were specified.");
		}

		if (Suspension::isSuspended($user)) { # check here (not above) to make sure others can't see the suspension
			throw new HttpException(403, NULL, 'Account is currently suspended.');
		}
		return true;
	}

	public static function getName($id)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT name FROM " . DB_TABLE_USERS . " WHERE id = UNHEX('" . $db_connection->real_escape_string($id) . "')";
		$db_result = $db_connection->query($db_query);

		while ($data = $db_result->fetch_assoc())
		{
			return $data['name'];
		}
		throw new HttpException(404, NULL, "User not found");
	}

	public static function getID($name)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT HEX(id) AS id FROM " . DB_TABLE_USERS . " WHERE name = '" . $db_connection->real_escape_string($name) . "'";
		$db_result = $db_connection->query($db_query);

		while ($data = $db_result->fetch_assoc())
		{
			return $data["id"];
		}
		throw new HttpException(404, NULL, "User not found");
	}

	public static function getPrivileges($id)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT privileges FROM " . DB_TABLE_USERS . " WHERE id = UNHEX('" . $db_connection->real_escape_string($id) . "')";
		$db_result = $db_connection->query($db_query);

		while ($data = $db_result->fetch_assoc())
		{
			return $data["privileges"];
		}
		throw new HttpException(404, NULL, "User not found");
	}

	public static function create($name, $mail, $pw) {
		$db_connection = db_ensure_connection();
		$name = $db_connection->real_escape_string($name);
		$mail = $db_connection->real_escape_string($mail);
		$pw = hash('sha256', $pw);

		$db_query = 'INSERT INTO ' . DB_TABLE_USERS . ' (`id`, `name`, `mail`, `pw`) VALUES (UNHEX(REPLACE(UUID(), "-", "")), "' . $name . '", "' . $mail . '", "' . $pw . '")';
		$db_connection->query($db_query);
	}
}
?>