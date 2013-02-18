<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../sql2array.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');

class Stdlib
{
	public static function GetItems($release)
	{
		$db_connection = db_ensure_connection();
		$release = mysql_real_escape_string($release, $db_connection);

		$db_query = 'SELECT HEX(`lib`) AS id, comment FROM ' . DB_TABLE_STDLIB . " WHERE `release` = '$release'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		return sql2array($db_result);
	}
}
?>