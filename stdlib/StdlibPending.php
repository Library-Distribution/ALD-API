<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');
require_once(dirname(__FILE__) . '/../modules/semver/semver.php');
require_once(dirname(__FILE__) . '/../UpdateType.php');
require_once(dirname(__FILE__) . '/../Item.php');
require_once(dirname(__FILE__) . '/../util.php');
require_once(dirname(__FILE__) . '/Stdlib.php');
require_once(dirname(__FILE__) . '/releases/StdlibRelease.php');
require_once(dirname(__FILE__) . '/../config/stdlib.php');

StdlibPending::cleanup();

class StdlibPending
{
	public static function GetAllEntries()
	{
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT HEX(`item`) AS id, comment, delay FROM ' . DB_TABLE_STDLIB_PENDING;
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		return sql2array($db_result);
	}

	public static function GetEntries($release) { # $release is not required to exist!
		$base = StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, StdlibRelease::PUBLISHED_YES);

		if ($base !== NULL) {
			$release_update = UpdateType::getUpdate($base, $release); # get release update type
			$old_items = Stdlib::GetItems($base); # get items in base
		} else { # in case there's no previous release
			$release_update = UpdateType::MAJOR; # this way, the first release can hold any suggested change (though there should only be ADD changes)
			$old_items = array(); # no release => no previous items
		}

		foreach ($old_items AS &$item) {
			$item = array_merge($item, Item::get($item['id'], array('name', 'version'))); # get name + version
		}

		$libs = self::GetAllEntries(); # get all pending changes
		$lib_version = array();

		foreach ($libs AS $i => &$lib) {
			$lib = array_merge($lib, Item::get($lib['id'], array('name', 'version'))); # get info on lib, especially name & version

			# assign the corresponding update types, comparing to the $old_items array
			#################################################
			$old = searchSubArray($old_items, array('name' => $lib['name'])); # what version of this item is in the old release?
			if ($old !== NULL) {
				if (semver_compare($old_items[$old]['version'], $lib['version']) == 0) { # same version means removal
					$update_type = UpdateType::REMOVE;
				} else if (semver_compare($old_items[$old]['version'], $lib['version']) == 1) { # if any of them means a downgrade (old > new), delete the entry
					if (!STDLIB_ALLOW_DOWNGRADE) {
						throw new HttpException(500);
					}
					$update_type = UpdateType::getUpdate($lib['version'], $old_items[$old]['version']);
				} else { # actually an update
					$update_type = UpdateType::getUpdate($old_items[$old]['version'], $lib['version']); # retrieve update type
				}
			} else { # not in latest release - must be new
				$update_type = UpdateType::ADD;
			}
			$lib['update'] = $update_type;
			#################################################

			# filter according to release update type
			#################################################
			$delayed = $lib['delay'] !== NULL && semver_compare($release, $lib['delay']) < 0;
			$include = false;
			switch ($release_update) {
				case UpdateType::MAJOR:  $include = !$delayed; # everything can go in a major release, just exclude delayed items
					break;
				case UpdateType::MINOR: $include = !$delayed && ($update_type == UpdateType::MINOR || $update_type == UpdateType::PATCH);
					break;
				case UpdateType::PATCH: $include = !$delayed && ($update_type == UpdateType::PATCH);
					break;
			}

			if ($include) {
				if (!isset($lib_version[$lib['name']]) || semver_compare($lib_version[$lib['name']], $lib['version']) < 0) { # if not duplicate, always take it || if duplicate: higher overwrites lower
					$lib_version[$lib['name']] = $lib['version'];
				}
			} else { # item update type does not fit stdlib release update
				unset($libs[$i]);
			}
			#################################################
		}
		return $libs;
	}

	public static function AddEntry($id, $comment)
	{
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);
		$comment = mysql_real_escape_string($comment, $db_connection);

		$db_query = 'INSERT INTO ' . DB_TABLE_STDLIB_PENDING . ' (`item`, `comment`) VALUES (UNHEX("' . $id . '"), "' . $comment . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}

	public static function DeleteEntry($id)
	{
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);

		$db_query = 'DELETE FROM ' . DB_TABLE_STDLIB_PENDING . " WHERE `item` = UNHEX('$id')";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}
	}

	public static function IsPending($id) {
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT * FROM ' . DB_TABLE_STDLIB_PENDING . ' WHERE `item` = UNHEX("' . $id . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return mysql_num_rows($db_result) > 0;
	}

	public static function SetComment($id, $comment) {
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);
		$comment = mysql_real_escape_string($comment, $db_connection);

		$db_query = 'UPDATE ' . DB_TABLE_STDLIB_PENDING . ' SET `comment` = "' . $comment . '" WHERE `item` = UNHEX("' . $id . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE || mysql_affected_rows() < 1) {
			throw new HttpException(500);
		}
	}

	public static function SetDelay($id, $delay = NULL) {
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);
		$delay = ($delay !== NULL) ? '"' . mysql_real_escape_string($delay, $db_connection) . '"' : 'NULL';

		$db_query = 'UPDATE ' . DB_TABLE_STDLIB_PENDING . ' SET `delay` = ' . $delay . ' WHERE `item` = UNHEX("' . $id . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE || mysql_affected_rows() < 1) {
			throw new HttpException(500);
		}
	}

	public static function cleanup() {
		$latest_release = StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, StdlibRelease::PUBLISHED_YES);
		if ($latest_release === NULL) {
			return; # we can't do any cleanup right now. When the TODOs below are implemented, they can be executed regardless of this.
		}

		$live_items = array_map(array('StdlibPending', 'sanitize_items'), Stdlib::GetItems($latest_release));
		$pending = array_map(array('StdlibPending', 'sanitize_items'), self::GetAllEntries());

		# ensure there are no pending entries < live entries if downgrades not allowed
		if (!STDLIB_ALLOW_DOWNGRADE) {
			$pending_copy = $pending;

			foreach ($live_items AS $item) {
				while (($i = searchSubArray($pending_copy, array('name' => $item['name']))) !== NULL) {
					if (semver_compare($item['version'], $pending_copy[$i]['version']) > 0) { # if the live version is > the pending (and no downgrades allowed, see above)
						self::DeleteEntry($pending_copy[$i]['id']); # delete the pending downgrade
					}
					unset($pending_copy[$i]);
				}
			}
		}

		# todo: ensure not removal + addition of the same item pending >> delete both
		# todo: ensure not removal + addition of different versions of the same item pending >> make upgrade
	}

	static function sanitize_items($item) {
		return array_merge($item, Item::get($item['id'], array('name', 'version')));
	}
}
?>