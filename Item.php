<?php
	require_once("db.php");
	require_once("HttpException.php");

	class Item
	{
		public static function getId($name, $version)
		{
			$db_connection = db_ensure_connection();
			$name = mysql_real_escape_string($name, $db_connection);
			$version = mysql_real_escape_string($version, $db_connection);

			$db_query = "SELECT HEX(id) FROM " . DB_TABLE_ITEMS . " WHERE name = '$name' AND version = '$version'";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, mysql_error());
			}
			if (mysql_num_rows($db_result) < 1)
			{
				throw new HttpException(404);
			}

			$db_entry = mysql_fetch_assoc($db_result);
			return $db_entry["HEX(id)"];
		}

		public static function existsId($id)
		{
			$db_connection = db_ensure_connection();
			$id = mysql_real_escape_string($id, $db_connection);

			$db_query = "SELECT COUNT(*) FROM " . DB_TABLE_ITEMS . " WHERE id = UNHEX('$id')";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, mysql_error());
			}

			$db_entry = mysql_fetch_assoc($db_result);
			return $db_entry["COUNT(*)"] > 0;
		}

		public static function exists($name, $version = NULL)
		{
			$db_connection = db_ensure_connection();
			$name = mysql_real_escape_string($name, $db_connection);
			$version = $version == NULL ? NULL : mysql_real_escape_string($version);

			$db_cond = "name = '$name'";
			if ($version != NULL)
			{
				$db_cond .= " AND version = '$version'";
			}

			$db_query = "SELECT COUNT(*) FROM " . DB_TABLE_ITEMS . " WHERE $db_cond";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, mysql_error());
			}

			$db_entry = mysql_fetch_assoc($db_result);
			return $db_entry["COUNT(*)"] > 0;
		}

		public static function getUser($name, $version)
		{
			return Item::getUserForId(Item::getId($name, $version));
		}

		public static function getUserForId($id)
		{
			$db_connection = db_ensure_connection();
			$id = mysql_real_escape_string($id, $db_connection);

			$db_query = "SELECT HEX(user) FROM " . DB_TABLE_ITEMS . " WHERE id = UNHEX('$id')";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, mysql_error());
			}

			$db_entry = mysql_fetch_assoc($db_result);
			return $db_entry["HEX(user)"];
		}
	}
?>