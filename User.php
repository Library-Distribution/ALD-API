<?php
require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . '/users/Suspension.php');
require_once(dirname(__FILE__) . "/modules/HttpException/HttpException.php");

class User
{
	const PRIVILEGE_NONE = 0;
	const PRIVILEGE_USER_MANAGE = 2;
	const PRIVILEGE_REVIEW = 4;
	const PRIVILEGE_DEFAULT_INCLUDE = 8;
	const PRIVILEGE_ADMIN = 16;
	const PRIVILEGE_REGISTRATION = 32;

	public static function privilegeToArray($privilege) {
		$arr = array();

		if ($privilege == self::PRIVILEGE_NONE) {
			$arr[] = 'none';
		}
		if (($privilege & self::PRIVILEGE_USER_MANAGE) == self::PRIVILEGE_USER_MANAGE) {
			$arr[] = 'user-mod';
		}
		if (($privilege & self::PRIVILEGE_REVIEW) == self::PRIVILEGE_REVIEW) {
			$arr[] = 'review';
		}
		if (($privilege & self::PRIVILEGE_DEFAULT_INCLUDE) == self::PRIVILEGE_DEFAULT_INCLUDE) {
			$arr[] = 'stdlib';
		}
		if (($privilege & self::PRIVILEGE_ADMIN) == self::PRIVILEGE_ADMIN) {
			$arr[] = 'admin';
		}
		if (($privilege & self::PRIVILEGE_REGISTRATION) == self::PRIVILEGE_REGISTRATION) {
			$arr[] = 'registration';
		}

		return $arr;
	}

	public static function hasPrivilegeById($id, $privilege)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT privileges FROM " . DB_TABLE_USERS . " WHERE id = UNHEX('" . mysql_real_escape_string($id, $db_connection) . "')";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		if (mysql_num_rows($db_result) != 1)
		{
			throw new HttpException(404, NULL, "User not found");
		}

		$data = mysql_fetch_object($db_result);
		return (((int)$data->privileges) & $privilege) == $privilege;
	}

	public static function hasPrivilege($name, $privilege)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT privileges FROM " . DB_TABLE_USERS . " WHERE name = '" .  mysql_real_escape_string($name, $db_connection) . "'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		if (mysql_num_rows($db_result) != 1)
		{
			throw new HttpException(404, NULL, "User not found");
		}

		$data = mysql_fetch_object($db_result);
		return (((int)$data->privileges) & $privilege) == $privilege;
	}

	public static function existsName($name)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT id FROM " . DB_TABLE_USERS . " WHERE name = '" . mysql_real_escape_string($name, $db_connection) . "'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}
		return mysql_num_rows($db_result) == 1;
	}

	public static function existsMail($mail)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT id FROM " . DB_TABLE_USERS . " WHERE mail = '" . mysql_real_escape_string($mail, $db_connection) . "'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}
		return mysql_num_rows($db_result) == 1;
	}

	public static function validateLogin($user, $pw)
	{
		$db_connection = db_ensure_connection();

		$pw = hash("sha256", $pw);
		$escaped_user = mysql_real_escape_string($user, $db_connection);

		$db_query = "SELECT pw FROM " . DB_TABLE_USERS . " WHERE name = '$escaped_user'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		if (mysql_num_rows($db_result) != 1)
		{
			throw new HttpException(403, NULL, "User not found");
		}

		$data = mysql_fetch_object($db_result);
		if ($data->pw != $pw)
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

		$db_query = "SELECT name FROM " . DB_TABLE_USERS . " WHERE id = UNHEX('" . mysql_real_escape_string($id, $db_connection) . "')";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		while ($data = mysql_fetch_object($db_result))
		{
			return $data->name;
		}
		throw new HttpException(404, NULL, "User not found");
	}

	public static function getID($name)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT HEX(id) AS id FROM " . DB_TABLE_USERS . " WHERE name = '" . mysql_real_escape_string($name, $db_connection) . "'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		while ($data = mysql_fetch_assoc($db_result))
		{
			return $data["id"];
		}
		throw new HttpException(404, NULL, "User not found");
	}

	public static function getPrivileges($id)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT privileges FROM " . DB_TABLE_USERS . " WHERE id = UNHEX('" . mysql_real_escape_string($id, $db_connection) . "')";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		while ($data = mysql_fetch_assoc($db_result))
		{
			return $data["privileges"];
		}
		throw new HttpException(404, NULL, "User not found");
	}

	public static function create($name, $mail, $pw) {
		$db_connection = db_ensure_connection();
		$name = mysql_real_escape_string($name, $db_connection);
		$mail = mysql_real_escape_string($mail, $db_connection);
		$pw = hash('sha256', $pw);

		$db_query = 'INSERT INTO ' . DB_TABLE_USERS . ' (`id`, `name`, `mail`, `pw`) VALUES (UNHEX(REPLACE(UUID(), "-", "")), "' . $name . '", "' . $mail . '", "' . $pw . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}
}
?>