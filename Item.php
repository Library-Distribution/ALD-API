<?php
	require_once(dirname(__FILE__) . "/db.php");
	require_once(dirname(__FILE__) . "/modules/HttpException/HttpException.php");
	require_once(dirname(__FILE__) . "/modules/semver/semver.php");
	require_once(dirname(__FILE__) . '/sql2array.php');
	require_once(dirname(__FILE__) . '/Assert.php');

	class Item
	{
		public static function getId($name, $version, $stable = NULL)
		{
			$db_connection = db_ensure_connection();
			$name = $db_connection->real_escape_string($name);
			$version = $db_connection->real_escape_string($version);

			$db_cond = "name = '$name'";
			if (!$special_version = in_array($version, array("latest", "first")))
			{
				$db_cond .= " AND version = '$version'";
			}
			if ($stable !== NULL) {
				$db_cond .= ' AND ' . ($stable ? '' : '!') . 'semver_stable(`version`)';
			}

			$db_query = 'SELECT HEX(id) AS id, version FROM ' . DB_TABLE_ITEMS . ' WHERE ' . $db_cond;
			$db_result = $db_connection->query($db_query);
			Assert::dbMinRows($db_result);

			if (!$special_version)
			{
				$db_entry = $db_result->fetch_assoc();
			}
			else
			{
				$items = sql2array($db_result);
				usort($items, array('Item', "semver_sort")); # sort by "version" field, following semver rules
				$db_entry = $items[$special_version == "latest" ? count($items) - 1 : 0];
			}

			return $db_entry["id"];
		}

		public static function get($id, array $cols)
		{
			$db_connection = db_ensure_connection();
			$id = $db_connection->real_escape_string($id);

			$db_query = 'SELECT ' . implode(', ', $c = array_map('EnwrapColName', $cols)) . ' FROM ' . DB_TABLE_ITEMS . " WHERE `id` = UNHEX('$id')";
			$db_result = $db_connection->query($db_query);
			return $db_result->fetch_assoc();
		}

		public static function existsId($id)
		{
			$db_connection = db_ensure_connection();
			$id = $db_connection->real_escape_string($id);

			$db_query = "SELECT COUNT(*) FROM " . DB_TABLE_ITEMS . " WHERE id = UNHEX('$id')";
			$db_result = $db_connection->query($db_query);

			$db_entry = $db_result->fetch_assoc();
			return $db_entry["COUNT(*)"] > 0;
		}

		public static function exists($name, $version = NULL)
		{
			$db_connection = db_ensure_connection();
			$name = $db_connection->real_escape_string($name);
			$version = $version == NULL ? NULL : $db_connection->real_escape_string($version);

			$db_cond = "name = '$name'";
			if ($version != NULL)
			{
				$db_cond .= " AND version = '$version'";
			}

			$db_query = "SELECT COUNT(*) FROM " . DB_TABLE_ITEMS . " WHERE $db_cond";
			$db_result = $db_connection->query($db_query);

			$db_entry = $db_result->fetch_assoc();
			return $db_entry["COUNT(*)"] > 0;
		}

		public static function getUser($name, $version)
		{
			return self::getUserForId(self::getId($name, $version));
		}

		public static function getUserForId($id)
		{
			$db_connection = db_ensure_connection();
			$id = $db_connection->real_escape_string($id);

			$db_query = "SELECT HEX(user) AS user FROM " . DB_TABLE_ITEMS . " WHERE id = UNHEX('$id')";
			$db_result = $db_connection->query($db_query);

			$db_entry = $db_result->fetch_assoc();
			return $db_entry["user"];
		}

		static function semver_sort($a, $b) {
			return semver_compare($a["version"], $b["version"]);
		}
	}

	function EnwrapColName($col) {
		return '`' . $col . '`';
	}
?>