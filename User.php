<?php
require_once dirname(__FILE__) . "/db.php";
require_once dirname(__FILE__) . '/Assert.php';
require_once dirname(__FILE__) . '/util/Privilege.php';
require_once dirname(__FILE__) . '/users/Suspension.php';
require_once dirname(__FILE__) . "/modules/HttpException/HttpException.php";

class User
{
	public static function hasPrivilegeById($id, $privilege)
	{
		return Privilege::contains(self::getPrivileges($id), $privilege);
	}

	public static function hasPrivilege($name, $privilege)
	{
		return self::hasPrivilegeById(self::getID($name), $privilege);
	}

	public static function addPrivilegeById($id, $privilege) {
		$db_connection = db_ensure_connection();

		$db_query = 'UPDATE ' . DB_TABLE_USERS . ' SET `privileges` = `privileges`|' . ((int)$privilege) . ' WHERE `id` = UNHEX("' . $db_connection->real_escape_string($id) . '")';
		$db_connection->query($db_query);
		Assert::dbMinRows($db_connection, 'User not found');
	}

	public static function removePrivilegeById($id, $privilege) {
		$db_connection = db_ensure_connection();

		$db_query = 'UPDATE ' . DB_TABLE_USERS . ' SET `privileges` = `privileges` & ~' . ((int)$privilege) . ' WHERE `id` = UNHEX("' . $db_connection->real_escape_string($id) . '")';
		$db_connection->query($db_query);
		Assert::dbMinRows($db_connection, 'User not found');
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
			throw new HttpException(403, "Invalid credentials were specified.");
		}

		if (Suspension::isSuspended($user)) { # check here (not above) to make sure others can't see the suspension
			throw new HttpException(403, 'Account is currently suspended.');
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
		throw new HttpException(404, "User not found");
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
		throw new HttpException(404, "User not found");
	}

	public static function getPrivileges($id)
	{
		$db_connection = db_ensure_connection();

		$db_query = "SELECT privileges FROM " . DB_TABLE_USERS . " WHERE id = UNHEX('" . $db_connection->real_escape_string($id) . "')";
		$db_result = $db_connection->query($db_query);

		while ($data = $db_result->fetch_assoc())
		{
			return (int)$data["privileges"];
		}
		throw new HttpException(404, "User not found");
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