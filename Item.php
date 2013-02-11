<?php
	require_once("db.php");
	require_once("HttpException.php");
	require_once("modules/semver/semver.php");

	class Item
	{
		public static function getId($name, $version)
		{
			$db_connection = db_ensure_connection();
			$name = mysql_real_escape_string($name, $db_connection);
			$version = mysql_real_escape_string($version, $db_connection);

			$db_cond = "name = '$name'";
			if (!$special_version = in_array($version, array("latest", "first")))
			{
				$db_cond .= " AND version = '$version'";
			}

			$db_query = 'SELECT HEX(id), version FROM ' . DB_TABLE_ITEMS . ' WHERE ' . $db_cond;
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, mysql_error());
			}
			if (mysql_num_rows($db_result) < 1)
			{
				throw new HttpException(404);
			}

			if (!$special_version)
			{
				$db_entry = mysql_fetch_assoc($db_result);
			}
			else
			{
				$items = array(); # fetch all items in an array
				while ($row = mysql_fetch_assoc($db_result))
				{
					$items[] = $row;
				}

				usort($items, "semver_sort"); # sort by "version" field, following semver rules
				$db_entry = $items[$special_version == "latest" ? count($items) - 1 : 0];
			}

			return $db_entry["HEX(id)"];
		}

		public static function get($id, array $cols)
		{
			$db_connection = db_ensure_connection();
			$id = mysql_real_escape_string($id, $db_connection);

			$db_query = 'SELECT ' . implode(', ', $c = array_map('EnwrapColName', $cols)) . ' FROM ' . DB_TABLE_ITEMS . " WHERE `id` = UNHEX('$id')";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, mysql_error());
			}
			return mysql_fetch_assoc($db_result);
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

	function semver_sort($a, $b)
	{
		return semver_compare($a["version"], $b["version"]);
	}

	function EnwrapColName($col) {
		return '`' . $col . '`';
	}
?>