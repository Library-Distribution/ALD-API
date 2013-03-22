<?php
require_once(dirname(__FILE__) . "/../../db.php");
require_once(dirname(__FILE__) . "/../Stdlib.php");
require_once(dirname(__FILE__) . "/../StdlibPending.php");
require_once(dirname(__FILE__) . '/../../UpdateType.php');
require_once(dirname(__FILE__) . "/../../modules/HttpException/HttpException.php");
require_once(dirname(__FILE__) . "/../../modules/semver/semver.php");

StdlibRelease::cleanup();

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
			if (count($releases) > 0)
			{
				usort($releases, array("StdlibRelease", "semver_sort")); # sort following the semver rules
				return $releases[$special_version == self::SPECIAL_VERSION_LATEST ? count($releases) - 1 : 0]; # latest / first release
			}
		}

		return NULL;
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
		$t = mysql_fetch_assoc($db_result);
		$t['published'] = (bool)$t['published'];
		return $t;
	}

	public static function create($release, $date = NULL, $description = '') {
		$db_connection = db_ensure_connection();

		$release = mysql_real_escape_string($release, $db_connection);
		$description = mysql_real_escape_string($description, $db_connection);
		$date = $date !== NULL ? '"' . mysql_real_escape_string($date, $db_connection) . '"' : 'NULL';

		$db_query = 'INSERT INTO ' . DB_TABLE_STDLIB_RELEASES . ' (`release`, `description`, `date`) VALUES ("' . $release . '", "' . $description . '", ' . $date . ')';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE  || mysql_affected_rows() != 1) {
			throw new HttpException(500, NULL, mysql_error());
		}
	}

	public static function delete($release)
	{
		$db_connection = db_ensure_connection();
		$release = mysql_real_escape_string($release, $db_connection);

		$db_query = "DELETE FROM " . DB_TABLE_STDLIB_RELEASES . " WHERE `release` = '$release' AND !`published`";
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
		if (self::exists($release, self::PUBLISHED_YES)) {
			throw new HttpException(400, NULL, 'Cannot update already published release!');
		}

		$db_connection = db_ensure_connection();
		$release = mysql_real_escape_string($release, $db_connection);

		$db_query = "UPDATE " . DB_TABLE_STDLIB_RELEASES . " Set "
				. implode(", ",
					array_map(
						create_function('$col, $val', 'return "`$col` = \'$val\'";'),
						array_keys($data),
						array_map('mysql_real_escape_string', array_values($data), array_fill(0, count($data), $db_connection))
						)
					)
				. " WHERE `release` = '$release' AND !`published`";

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

	public static function previousRelease($release, $published) {
		$releases = self::ListReleases(self::PUBLISHED_BOTH);
		usort($releases, array('StdlibRelease', 'semver_sort'));
		$index = array_search($release, $releases);
		if ($index !== FALSE) {
			while ($index >= 1) {
				$prev_release = $releases[--$index];
				if (self::exists($prev_release, $published))
					return $prev_release;
			}
		}
		return NULL;
	}

	public static function publishPending() {
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT `release` FROM ' . DB_TABLE_STDLIB_RELEASES . ' WHERE !`published` AND `date` <= NOW()';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500, NULL, mysql_error());
		}

		$releases = array();
		while ($release = mysql_fetch_array($db_result)) { # sort by release
			$releases[] = $release['release'];
		}

		usort($releases, array('StdlibRelease', 'semver_sort')); # sort following the semver rules
		foreach ($releases AS $release) {
			self::publish($release);
		}
	}

	public static function publish($release) {
		if (self::exists($release, self::PUBLISHED_YES)) {
			throw new HttpException(400, NULL, 'Cannot publish already published release!');
		}

		$entries = Stdlib::GetItems($release);
		foreach ($entries AS $entry) {
			Stdlib::writeEntry($release, $entry['id'], $entry['comment']);
			StdlibPending::DeleteEntry($entry['id']);
		}

		# removals are not covered by deletion above, so delete these entries here
		$pending = StdlibPending::GetEntries($release);
		foreach ($pending AS $entry) {
			if ($entry['update'] == UpdateType::REMOVE) {
				StdlibPending::DeleteEntry($entry['id']);
			}
		}

		$release_data = self::describe($release, self::PUBLISHED_BOTH);
		if ($release_data['date'] === NULL) {
			self::update($release, array('date' => date('Y-m-d H:i:s')));
		}
		self::update($release, array('published' => true));
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
		return sql2array($db_result, create_function('$release', 'return $release[\'release\'];'));
	}

	public static function cleanup() {
		$latest_release = self::getVersion(self::SPECIAL_VERSION_LATEST, self::PUBLISHED_YES);

		# ensure everything that should be published is published
		self::publishPending();

		# ensure there are no downgrade releases
		$releases = self::ListReleases(self::PUBLISHED_NO);
		foreach ($releases AS $release) {
			if (semver_compare($latest_release, $release) > -1) {
				self::delete($release);
			}
		}
	}

	const PUBLISHED_YES = 1;
	const PUBLISHED_NO = 2;
	const PUBLISHED_BOTH = 3; # self::PUBLISHED_YES | self::PUBLISHED_NO

	static function get_publish_cond($published)
	{
		switch ($published)
		{
			case self::PUBLISHED_YES:
				return '`published`';
			case self::PUBLISHED_NO:
				return '!`published`';
			case self::PUBLISHED_BOTH:
				return NULL;
			default:
				throw new HttpException(400);
		}
	}
}

?>