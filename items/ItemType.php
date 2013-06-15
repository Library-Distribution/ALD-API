<?php
require_once dirname(__FILE__) . '/../db.php';
require_once dirname(__FILE__) . '/../modules/HttpException/HttpException.php';
require_once dirname(__FILE__) . '/../sql2array.php';
require_once dirname(__FILE__) . '/../Assert.php';

class ItemType
{
	public static function getCode($name)
	{
		$db_connection = db_ensure_connection();

		$db_query = 'SELECT code FROM ' . DB_TABLE_TYPES . ' WHERE name = \'' . $db_connection->real_escape_string($name) . '\'';
		$db_result = $db_connection->query($db_query, MYSQLI_STORE_RESULT, 'Could not read item type code');
		Assert::dbMinRows($db_result, 'Item type "' . $name . '" is not supported!', 400);

		$row = $db_result->fetch_assoc();
		return $row['code'];
	}

	public static function getName($code)
	{
		$db_connection = db_ensure_connection();

		$db_query = 'SELECT name FROM ' . DB_TABLE_TYPES . ' WHERE code = \'' . $db_connection->real_escape_string($code) . '\'';
		$db_result = $db_connection->query($db_query, MYSQLI_STORE_RESULT, 'Could not read item type name');
		Assert::dbMinRows($db_result, 'Item type "' . $code . '" is unknown!', 500);

		$row = $db_result->fetch_assoc();
		return $row['name'];
	}

	public static function getAllNames()
	{
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT name FROM ' . DB_TABLE_TYPES;
		$db_result = $db_connection->query($db_query, MYSQLI_STORE_RESULT, 'Could not read supported item types');

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