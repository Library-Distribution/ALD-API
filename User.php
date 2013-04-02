<?php
require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . '/Assert.php');
require_once(dirname(__FILE__) . '/users/Suspension.php');
require_once(dirname(__FILE__) . "/modules/HttpException/HttpException.php");

class User
{
	const PRIVILEGE_NONE = 0;
	const PRIVILEGE_MODERATOR = 2;
	const PRIVILEGE_REVIEW = 4;
	const PRIVILEGE_STDLIB = 8;
	const PRIVILEGE_ADMIN = 16;
	const PRIVILEGE_REGISTRATION = 32;
	const PRIVILEGE_STDLIB_ADMIN = 64;

	public static function privilegeToArray($privilege) {
		$arr = array();

		if ($privilege == self::PRIVILEGE_NONE) {
			$arr[] = 'none';
		}
		if (($privilege & self::PRIVILEGE_MODERATOR) == self::PRIVILEGE_MODERATOR) {
			$arr[] = 'user-mod';
		}
		if (($privilege & self::PRIVILEGE_REVIEW) == self::PRIVILEGE_REVIEW) {
			$arr[] = 'review';
		}
		if (($privilege & self::PRIVILEGE_STDLIB) == self::PRIVILEGE_STDLIB) {
			$arr[] = 'stdlib';
		}
		if (($privilege & self::PRIVILEGE_STDLIB_ADMIN) == self::PRIVILEGE_STDLIB_ADMIN) {
			$arr[] = 'stdlib-admin';
		}
		if (($privilege & self::PRIVILEGE_ADMIN) == self::PRIVILEGE_ADMIN) {
			$arr[] = 'admin';
		}
		if (($privilege & self::PRIVILEGE_REGISTRATION) == self::PRIVILEGE_REGISTRATION) {
			$arr[] = 'registration';
		}

		return $arr;
	}

	public static function privilegeFromArray($arr) {
		$privilege = self::PRIVILEGE_NONE;

		foreach ($arr AS $priv) {
			switch ($priv) {
				case 'none':
					if (count($arr) > 1) {
						throw new HttpException(500);
					}
					break;
				case 'user-mod': $privilege |= self::PRIVILEGE_MODERATOR;
					break;
				case 'review': $privilege |= self::PRIVILEGE_REVIEW;
					break;
				case 'stdlib': $privilege |= self::PRIVILEGE_STDLIB;
					break;
				case 'stdlib-admin': $privilege |= self::PRIVILEGE_STDLIB_ADMIN;
					break;
				case 'admin': $privilege |= self::PRIVILEGE_ADMIN;
					break;
				case 'registration': $privilege |= self::PRIVILEGE_REGISTRATION;
					break;
				default:
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