<?php
	require_once("db.php");
	require_once("HttpException.php");

	class UpdateType
	{
		public static function getCode($str, $usage)
		{
			$db_connection = db_ensure_connection();

			$str = mysql_real_escape_string($str, $db_connection);
			$usage = mysql_real_escape_string($usage, $db_connection);

			$db_query = "SELECT id FROM " . DB_TABLE_UPDATE_TYPE . " WHERE name = '$str' AND `$usage` = TRUE";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500);
			}
			if (mysql_num_rows($db_result) != 1)
			{
				throw new HttpException(400);
			}

			$db_entry = mysql_fetch_assoc($db_result);
			return $db_entry["id"];
		}
	}
?>