<?php
	require_once(dirname(__FILE__) . "/modules/HttpException/HttpException.php");
	require_once(dirname(__FILE__) . "/config/database.php"); # import database settings

	final class proxy_mysqli extends mysqli {
		public function query($db_query, $resultmode = MYSQLI_STORE_RESULT, $msg = '') {
			$db_result = parent::query($db_query, $resultmode);
			if ($db_result === FALSE) {
				throw new HttpException(500, NULL, ($msg ? $msg . ': ' : '') . $this->error);
			}
			return $db_result;
		}
	}

	function db_ensure_connection()
	{
		static $connection = false;

		if (!$connection)
		{
			$connection = new proxy_mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
			if ($connection->connect_error || mysqli_connect_error() || $connection->error)
			{
				throw new HttpException(500);
			}
		}
		return $connection;
	}
?>