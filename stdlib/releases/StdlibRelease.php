<?php
require_once(dirname(__FILE__) . "/../../db.php");
require_once(dirname(__FILE__) . '/../../SortHelper.php');
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

		$db_query = "SELECT * FROM " . DB_TABLE_STDLIB_RELEASES . " WHERE `release` = '" . $db_connection->real_escape_string($release) . "'" . $db_cond;
		$db_result = $db_connection->query($db_query);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, $db_connection->error);
		}
		return $db_result->num_rows > 0;
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

		$db_query = "SELECT * FROM " . DB_TABLE_STDLIB_RELEASES . " WHERE `release` = '" . $db_connection->real_escape_string($release) . "'" . $db_cond;
		$db_result = $db_connection->query($db_query);
		if (!$db_result)
		{
			throw new HttpException(500);
		}
		if ($db_result->num_rows != 1)
		{
			throw new HttpException(404);
		}
		$t = $db_result->fetch_assoc();
		$t['published'] = (bool)$t['published'];
		return $t;
	}

	public static function create($release, $date = NULL, $description = '') {
		$db_connection = db_ensure_connection();

		$release = $db_connection->real_escape_string($release);
		$description = $db_connection->real_escape_string($description);
		$date = $date !== NULL ? '"' . $db_connection->real_escape_string($date) . '"' : 'NULL';

		$db_query = 'INSERT INTO ' . DB_TABLE_STDLIB_RELEASES . ' (`release`, `description`, `date`) VALUES ("' . $release . '", "' . $description . '", ' . $date . ')';
		$db_result = $db_connection->query($db_query);
		if ($db_result === FALSE  || $db_connection->affected_rows != 1) {
			throw new HttpException(500, NULL, $db_connection->error);
		}
	}

	public static function delete($release)
	{
		$db_connection = db_ensure_connection();
		$release = $db_connection->real_escape_string($release);

		$db_query = "DELETE FROM " . DB_TABLE_STDLIB_RELEASES . " WHERE `release` = '$release' AND !`published`";
		$db_result = $db_connection->query($db_query);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, $db_connection->error);
		}
		else if ($db_connection->affected_rows < 1)
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
		$release = $db_connection->real_escape_string($release);

		$db_query = "UPDATE " . DB_TABLE_STDLIB_RELEASES . " Set "
				. implode(", ",
					array_map(
						create_function('$col, $val', 'return "`$col` = \'$val\'";'),
						array_keys($data),
						array_map(array($db_connection, 'real_escape_string'), array_values($data))
						)
					)
				. " WHERE `release` = '$release' AND !`published`";

		$db_result = $db_connection->query($db_query);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, $db_connection->error);
		}
		else if ($db_connection->affected_rows != 1)
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
		$db_result = $db_connection->query($db_query);
		if ($db_result === FALSE) {
			throw new HttpException(500, NULL, $db_connection->error);
		}

		$releases = array();
		while ($release = $db_result->fetch_array()) { # sort by release
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

	public static function ListReleases($published, $sort = array())
	{
		# take publishing status into account
		$db_cond = ($t = self::get_publish_cond($published)) == NULL ? '' : " WHERE $t";
		$db_connection = db_ensure_connection();

		# support sorting
		$db_join = ' ';
		$db_sort = SortHelper::getOrderClause($sort, array('date' => '`date`', 'release' => '`position`'));
		if (array_key_exists('release', $sort)) { # sorting with semver needs special setup
			SortHelper::PrepareSemverSorting(DB_TABLE_STDLIB_RELEASES, 'release', $db_cond);
			$db_join = ' LEFT JOIN (`semver_index`) ON (`' . DB_TABLE_STDLIB_RELEASES . '`.`release` = `semver_index`.`version`) ';
		}

		# get all releases from DB
		$db_query = "SELECT `release` FROM " . DB_TABLE_STDLIB_RELEASES . $db_join . $db_cond . $db_sort;
		$db_result = $db_connection->query($db_query);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, $db_connection->error);
		}

		# fetch releases in array
		return sql2array($db_result, create_function('$release', 'return $release[\'release\'];'));
	}

	public static function cleanup() {
		# ensure everything that should be published is published
		self::publishPending();

		$latest_release = self::getVersion(self::SPECIAL_VERSION_LATEST, self::PUBLISHED_YES);

		if ($latest_release !== NULL) {
			# ensure there are no downgrade releases (only if there's actually a published release)
			$releases = self::ListReleases(self::PUBLISHED_NO);
			foreach ($releases AS $release) {
				if (semver_compare($latest_release, $release) > -1) {
					self::delete($release);
				}
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