<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');

class StdlibPending
{
	public static function GetEntries()
	{
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT HEX(`lib`) AS lib FROM ' . DB_TABLE_STDLIB_PENDING;
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		return sql2array($db_result, create_function('$lib', 'return $lib[\'lib\'];'));
	}

	public static function AddEntry($id)
	{
	}

	public static function DeleteEntry($id)
	{
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);

		$db_query = 'DELETE * FROM ' . DB_TABLE_STDLIB_PENDING . " WHERE `id` = UNHEX('$id')";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}
	}
}
?>