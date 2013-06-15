<?php
	require_once dirname(__FILE__) . "/db.php";
	require_once dirname(__FILE__) . "/modules/HttpException/HttpException.php";
	require_once dirname(__FILE__) . '/sql2array.php';
	require_once dirname(__FILE__) . '/Assert.php';
	require_once dirname(__FILE__) . '/SortHelper.php';

	class Item
	{
		public static function getId($name, $version, $stable = NULL)
		{
			$db_connection = db_ensure_connection();
			$name = $db_connection->real_escape_string($name);
			$version = $db_connection->real_escape_string($version);

			$db_cond = "name = '$name'";
			$db_join = $db_order = '';
			if (!$special_version = in_array($version, array("latest", "first")))
			{
				$db_cond .= " AND version = '$version'";
			} else {
				$db_order = ' ORDER BY `position`' . ($version == 'latest' ? 'DESC' : 'ASC');
				$db_join = ' LEFT JOIN `semver_index` USING (`version`)';
				SortHelper::PrepareSemverSorting(DB_TABLE_ITEMS, 'version');
			}

			if ($stable !== NULL) {
				$db_cond .= ' AND ' . ($stable ? '' : '!') . 'semver_stable(`version`)';
			}

			$db_query = 'SELECT HEX(id) AS id, version FROM ' . DB_TABLE_ITEMS . $db_join . ' WHERE ' . $db_cond . $db_order;
			$db_result = $db_connection->query($db_query);
			Assert::dbMinRows($db_result);

			$db_entry = $db_result->fetch_assoc(); # the only (exact version) or first ("latest" or "first" version) entry
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

		const REVIEW_GOOD = 1;
		const REVIEW_INDETERMINATE = 0;
		const REVIEW_BAD = -1;

		public static function IsReviewed($id) {
			$db_connection = db_ensure_connection();
			$id = $db_connection->real_escape_string($id);

			$db_query = 'SELECT `reviewed` FROM `' . DB_TABLE_ITEMS . '` WHERE `id` = UNHEX("' . $id . '")';
			$db_result = $db_connection->query($db_query);
			Assert::dbMinRows($db_result);

			$db_entry = $db_result->fetch_assoc();
			return (int)$db_entry['reviewed'] != self::REVIEW_INDETERMINATE;
		}

		public static function Review($id, $status) {
			$db_connection = db_ensure_connection();
			$id = $db_connection->real_escape_string($id);
			$status = (int)$status;

			if (!in_array($status, array(self::REVIEW_GOOD, self::REVIEW_INDETERMINATE, self::REVIEW_BAD))) {
				throw new HttpException(500);
			}

			$db_query = 'UPDATE `' . DB_TABLE_ITEMS . '` SET `reviewed` = ' . $status . ' WHERE `id` = UNHEX("' . $id . '")';
			$db_result = $db_connection->query($db_query);
			Assert::dbMinRows($db_connection);
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
	}

	function EnwrapColName($col) {
		return '`' . $col . '`';
	}
?>