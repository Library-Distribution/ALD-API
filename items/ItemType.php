<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');
require_once(dirname(__FILE__) . '/../sql2array.php');

class ItemType
{
	public static function getCode($name)
	{
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT code FROM ' . DB_TABLE_TYPES . ' WHERE name = \'' . $db_connection->real_escape_string($name) . '\'';
		$db_result = $db_connection->query($db_query);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, 'Could not read item type code: ' . $db_connection->error);
		}

		if ($db_result->num_rows < 1)
		{
			throw new HttpException(400, NULL, "Item type '$name' is not supported!");
		}
		$row = $db_result->fetch_assoc();
		return $row['code'];
	}

	public static function getName($code)
	{
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT name FROM ' . DB_TABLE_TYPES . ' WHERE code = \'' . $db_connection->real_escape_string($code) . '\'';
		$db_result = $db_connection->query($db_query);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, 'Could not read item type name: ' . $db_connection->error);
		}

		if ($db_result->num_rows < 1)
		{
			throw new HttpException(500, NULL, "Item type '$code' is unknown!");
		}
		$row = $db_result->fetch_assoc();
		return $row['name'];
	}

	public static function getAllNames()
	{
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT name FROM ' . DB_TABLE_TYPES;
		$db_result = $db_connection->query($db_query);
		if ($db_result === FALSE)
		{
			throw new HttpException(500, NULL, 'Could not read supported item types!');
		}

		return sql2array($db_result, create_function('$entry', 'return $entry[\'name\'];'));
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