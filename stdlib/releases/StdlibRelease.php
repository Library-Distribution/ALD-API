<?php
require_once("../../db.php");
require_once("../../modules/HttpException/HttpException.php");
require_once("../../modules/semver/semver.php");

class StdlibRelease
{
	const RELEASE_BASE_ALL = "all";
	const RELEASE_BASE_PUBLISHED = "published";

	public static function exists($release, $published)
	{
		$db_cond = ($t = self::get_publish_cond($published)) == NULL ? '' : " AND $t";
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

	public static function getVersion($special_version, $published)
	{
		$special_version = strtolower($special_version);

		if (in_array($special_version, array(self::SPECIAL_VERSION_LATEST, self::SPECIAL_VERSION_FIRST)))
		{
			$releases = self::ListReleases($published);
			if (count($releases > 0))
			{
				usort($releases, array("StdlibRelease", "semver_sort")); # sort following the semver rules
				return $releases[$special_version == self::SPECIAL_VERSION_LATEST ? count($releases) - 1 : 0]; # latest / first release
			}
		}

		return FALSE;
	}

	public static function describe($release, $published)
	{
		# resolve special release versions
		if (in_array(strtolower($release), array(self::SPECIAL_VERSION_LATEST, self::SPECIAL_VERSION_FIRST)))
		{
			$release = self::getVersion($release, $published);
			if (!$release)
				throw new HttpException(404);
		}

		$db_cond = ($t = self::get_publish_cond($published)) == NULL ? '' : " AND $t";
		$db_connection = db_ensure_connection();

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

		$db_query = "DELETE FROM " . DB_TABLE_STDLIB_RELEASES . " WHERE `release` = '$release' AND " . self::get_publish_cond(self::PUBLISHED_NO);
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
				. " WHERE `release` = '$release' AND " . self::get_publish_cond(self::PUBLISHED_NO);

		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error());
		}
		else if (mysql_affected_rows($db_connection) != 1)
		{
			throw new HttpException(400, NULL, "Release '$release' doesn't exist or is already published.");
		}
	}

	static function semver_sort($a, $b)
	{
		return semver_compare($a, $b);
	}

	public static function ListReleases($published)
	{
		# take publishing status into account
		$db_cond = ($t = self::get_publish_cond($published)) == NULL ? '' : " WHERE $t";
		$db_connection = db_ensure_connection();

		# get all releases from DB
		$db_query = "SELECT `release` FROM " . DB_TABLE_STDLIB_RELEASES . $db_cond;
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error());
		}

		# fetch releases in array
		$releases = array();
		while ($release = mysql_fetch_assoc($db_result))
		{
			$releases[] = $release["release"];
		}
		return $releases;
	}

	const PUBLISHED_YES = 1;
	const PUBLISHED_NO = 2;
	const PUBLISHED_BOTH = 3; # self::PUBLISHED_YES | self::PUBLISHED_NO

	static function get_publish_cond($published)
	{
		switch ($published)
		{
			case self::PUBLISHED_YES:
				return '(`date` AND NOW() > `date`)';
			case self::PUBLISHED_NO:
				return '(!`date` OR NOW() < `date`)';
			case self::PUBLISHED_BOTH:
				return NULL;
			default:
				throw new HttpException(400);
		}
	}
}

?>