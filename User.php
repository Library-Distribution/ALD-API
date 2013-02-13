<?php
require_once("db.php");
require_once(dirname(__FILE__) . '/users/Suspension.php');
require_once("modules/HttpException/HttpException.php");

class User
{
	const PRIVILEGE_NONE = 0;
	const PRIVILEGE_USER_MANAGE = 2;
	const PRIVILEGE_REVIEW = 4;
	const PRIVILEGE_DEFAULT_INCLUDE = 8;
	const PRIVILEGE_ADMIN = 16;

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

	public static function validateLogin($user, $pw, $throw = true)
	{
		$db_connection = db_ensure_connection();

		$pw = hash("sha256", $pw);
		$escaped_user = mysql_real_escape_string($user, $db_connection);

		$db_query = "SELECT pw FROM " . DB_TABLE_USERS . " WHERE name = '$escaped_user'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			if (!$throw)
			{
				return false;
			}
			throw new HttpException(500);
		}

		if (mysql_num_rows($db_result) != 1)
		{
			if (!$throw)
			{
				return false;
			}
			throw new HttpException(403, NULL, "User not found");
		}

		$data = mysql_fetch_object($db_result);
		if ($data->pw != $pw)
		{
			if (!$throw)
			{
				return false;
			}
			throw new HttpException(403, NULL, "Invalid credentials were specified.");
		}

		if (Suspension::isSuspended($user)) { # check here (not above) to make sure others can't see the suspension
			if (!$throw) {
				return false;
			}
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

	public static function getToken($name)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT activationToken FROM " . DB_TABLE_USERS . " WHERE name = '" . mysql_real_escape_string($name, $db_connection) . "'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		while ($data = mysql_fetch_object($db_result))
		{
			return $data->activationToken;
		}
		throw new HttpException(404, NULL, "User not found");
	}

	public static function setToken($name, $token)
	{
		$db_connection = db_ensure_connection();

		$db_query = "UPDATE " . DB_TABLE_USERS . " Set activationToken = '" . mysql_real_escape_string($token, $db_connection) . "' WHERE name = '" . mysql_real_escape_string($name, $db_connection) . "'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		if (mysql_affected_rows() != 1)
		{
			throw new HttpException(404, NULL, "User not found");
		}
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
}
?>