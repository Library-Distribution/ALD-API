<?php
	require_once(dirname(__FILE__) . "/modules/HttpException/HttpException.php");
	require_once(dirname(__FILE__) . "/config/database.php"); # import database settings

	function db_ensure_connection()
	{
		static $connection = false;

		if (!$connection)
		{
			$connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
			if ($connection->connect_error || mysqli_connect_error() || $connection->error)
			{
				throw new HttpException(500);
			}
			$connection->set_charset('latin1');
		}
		return $connection;
	}
?>