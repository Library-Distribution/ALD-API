<?php
	require_once(dirname(__FILE__) . "/modules/HttpException/HttpException.php");
	require_once(dirname(__FILE__) . "/config/database.php"); # import database settings

	function db_ensure_connection()
	{
		static $connection = false;

		if (!$connection)
		{
			$connection = mysql_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD);
			if (!$connection)
			{
				throw new HttpException(500);
			}
			if (!mysql_select_db(DB_NAME, $connection))
			{
				throw new HttpException(500);
			}
		}
		return $connection;
	}
?>