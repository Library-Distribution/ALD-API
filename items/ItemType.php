<?php
require_once('../db.php');
require_once('../HttpException.php');

class ItemType
{
	public static function getCode($name)
	{
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT code FROM ' . DB_TABLE_TYPES . ' WHERE name = \'' . mysql_real_escape_string($name, $db_connection) . '\'';
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, 'Could not read item type code: ' . mysql_error());
		}

		if (mysql_num_rows($db_result) < 1)
		{
			throw new HttpException(400, NULL, "Item type '$name' is not supported!");
		}
		$row = mysql_fetch_array($db_result);
		return $row['code'];
	}

	public static function getName($code)
	{
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT name FROM ' . DB_TABLE_TYPES . ' WHERE code = \'' . mysql_real_escape_string($code, $db_connection) . '\'';
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, 'Could not read item type name: ' . mysql_error());
		}

		if (mysql_num_rows($db_result) < 1)
		{
			throw new HttpException(500, NULL, "Item type '$code' is unknown!");
		}
		$row = mysql_fetch_array($db_result);
		return $row['name'];
	}

	public static function isSupported($name)
	{
		try
		{
			self::getCode($name);
		}
		catch (HttpException $e)
		{
			return false;
		}
		return true;
	}
}
?>