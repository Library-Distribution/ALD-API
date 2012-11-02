<?php
require_once("../../db.php");
require_once("../../HttpException.php");
require_once("../../semver.php");

class StdlibRelease
{
	const RELEASE_BASE_ALL = "all";
	const RELEASE_BASE_PUBLISHED = "published";

	public static function exists($release, $published_only = false)
	{
		$db_cond = "";
		if ($published_only)
		{
			$db_cond = " AND (date AND NOW() > date)";
		}

		$db_connection = db_ensure_connection();
		$db_query = "SELECT * FROM " . DB_TABLE_STDLIB_RELEASES . " WHERE `release` = '" . mysql_real_escape_string($release) . "'" . $db_cond;
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error());
		}
		return mysql_num_rows($db_result) > 0;
	}

	const SPECIAL_VERSION_LATEST = "latest";
	const SPECIAL_VERSION_FIRST = "first";

	public static function getVersion($special_version, $published_only = false)
	{
		$db_connection = db_ensure_connection();
		$special_version = strtolower($special_version);

		if (in_array($special_version, array(self::SPECIAL_VERSION_LATEST, self::SPECIAL_VERSION_FIRST)))
		{
			# get all releases
			$db_cond = $published_only ? " WHERE date AND NOW() > date" : "";
			$db_query = "SELECT `release` FROM " . DB_TABLE_STDLIB_RELEASES . $db_cond;
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, mysql_error());
			}

			# no latest release
			if (mysql_num_rows($db_result) < 1)
			{
				return FALSE;
			}

			# fetch releases in array
			$releases = array();
			while ($release = mysql_fetch_assoc($db_result))
			{
				$releases[] = $release;
			}

			usort($releases, array("StdlibRelease", "semver_sort")); # sort by "release" field, following semver rules
			$db_entry = $releases[$special_version == self::SPECIAL_VERSION_LATEST ? count($releases) - 1 : 0]; # latest / first release
			return $db_entry["release"];
		}
		return FALSE;
	}

	public static function describe($release, $published_only = false)
	{
		$db_connection = db_ensure_connection();

		# resolve special release versions
		if (in_array(strtolower($release), array(self::SPECIAL_VERSION_LATEST, self::SPECIAL_VERSION_FIRST)))
		{
			$release = self::getVersion($release, $published_only);
			if (!$release)
				throw new HttpException(404);
		}

		$db_cond = $published_only ? " AND (date AND NOW() > date)" : "";
		$db_query = "SELECT * FROM " . DB_TABLE_STDLIB_RELEASES . " WHERE `release` = '" . mysql_real_escape_string($release) . "'" . $db_cond;
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}
		if (mysql_num_rows($db_result) != 1)
		{
			throw new HttpException(404);
		}
		return mysql_fetch_assoc($db_result);
	}

	public static function delete($release)
	{
		$db_connection = db_ensure_connection();
		$release = mysql_real_escape_string($release, $db_connection);

		$db_query = "DELETE FROM " . DB_TABLE_STDLIB_RELEASES . " WHERE `release` = '$release' AND (!date OR NOW() < date)";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error());
		}
		else if (mysql_affected_rows($db_connection) < 1)
		{
			throw new HttpException(400, NULL, "Release doesn't exist or is already published.");
		}
	}

	public static function update($release, $data)
	{
		$db_connection = db_ensure_connection();
		$release = mysql_real_escape_string($release, $db_connection);

		$db_query = "UPDATE " . DB_TABLE_STDLIB_RELEASES . " Set "
				. implode(", ",
					array_map(
						function($col, $val) { return "`$col` = '" . mysql_real_escape_string($val) . "'"; },
						array_keys($data),
						array_values($data)
						)
					)
				. " WHERE `release` = '$release'";

		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error());
		}
		else if (mysql_affected_rows($db_connection) != 1)
		{
			throw new HttpException(400, NULL, "Release doesn't exist or is already published.");
		}
	}

	static function semver_sort($a, $b)
	{
		return semver_compare($a["release"], $b["release"]);
	}
}

?>