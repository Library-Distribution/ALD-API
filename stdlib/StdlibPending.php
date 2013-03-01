<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');
require_once(dirname(__FILE__) . '/../UpdateType.php');
require_once(dirname(__FILE__) . '/../Item.php');
require_once(dirname(__FILE__) . '/../util.php');
require_once(dirname(__FILE__) . '/Stdlib.php');
require_once(dirname(__FILE__) . '/releases/StdlibRelease.php');

class StdlibPending
{
	public static function GetAllEntries()
	{
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT HEX(`lib`) AS id, comment FROM ' . DB_TABLE_STDLIB_PENDING;
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		return sql2array($db_result);
	}

	public static function GetEntries($release) {
		$base = StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, StdlibRelease::PUBLISHED_YES);
		$release_update = UpdateType::getUpdate($base, $release); # get release update type

		$old_items = Stdlib::GetItems($base); # get items in base
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
					self::DeleteEntry($lib['id']);
					unset($libs[$i]);
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
			$include = false;
			switch ($release_update) {
				case UpdateType::MAJOR: 	$include = true; # everything can go in a major release
					break;
				case UpdateType::MINOR: $include = $update_type == UpdateType::MINOR || $update_type == UpdateType::PATCH;
					break;
				case UpdateType::PATCH: $include = $update_type == UpdateType::PATCH;
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

		$db_query = 'INSERT INTO ' . DB_TABLE_STDLIB_PENDING . ' (`lib`, `comment`) VALUES (UNHEX("' . $id . '"), "' . $comment . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}
	}

	public static function DeleteEntry($id)
	{
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);

		$db_query = 'DELETE FROM ' . DB_TABLE_STDLIB_PENDING . " WHERE `lib` = UNHEX('$id')";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}
	}

	public static function IsPending($id) {
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT * FROM ' . DB_TABLE_STDLIB_PENDING . ' WHERE `lib` = UNHEX"' . $id . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return mysql_num_rows($db_result) > 0;
	}
}
?>