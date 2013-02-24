<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../sql2array.php');
require_once(dirname(__FILE__) . '/../util.php');
require_once(dirname(__FILE__) . '/StdlibPending.php');
require_once(dirname(__FILE__) . '/releases/StdlibRelease.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');

class Stdlib
{
	public static function GetItems($release, $suppress_publish = false)
	{
		if (StdlibRelease::exists($release, StdlibRelease::PUBLISHED_YES)) { # check if published
			$db_connection = db_ensure_connection();
			$release = mysql_real_escape_string($release, $db_connection);

			$db_query = 'SELECT HEX(`lib`) AS id, comment FROM ' . DB_TABLE_STDLIB . " WHERE `release` = '$release'";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500);
			}

			$items = sql2array($db_result);
			if (!$suppress_publish && count($items) == 0) {
				try {
					if ($retry = count(StdlibPending::GetEntries($release)) > 0)
						StdlibRelease::publish($release);
				} catch (HttpException $e) {
					$retry = false;
				}
				if ($retry) {
					return self::GetItems($release, true); # avoid never-ending loop
				}
			}
			return $items;
		} else {
			return self::GetItemsUnpublished($release, StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, StdlibRelease::PUBLISHED_YES));
		}
	}

	public static function GetItemsUnpublished($release, $base) {
		$old_items = self::GetItems($base);
		foreach ($old_items AS &$item) {
			$item = array_merge($item, Item::get($item['id'], array('name', 'version'))); # get name + version
		}

		$pending = StdlibPending::GetEntries($release);

		foreach ($pending AS &$entry) {
			switch ($entry['update']) {
				case UpdateType::REMOVE:
					$index = searchSubArray($old_items, array('id' => $entry['id']));
					if ($index === NULL)
						throw new HttpException(500);
					unset($old_items[$index]);
					break;
				case UpdateType::ADD:
					unset($entry['update']);
					$old_items[] = $entry;
					break;
				default:
					unset($entry['update']);
					$index = searchSubArray($old_items, array('name' => $entry['name']));
					if ($index === NULL)
						throw new HttpException(500);
					$old_items[$index] = $entry;
					break;
			}
		}

		sort($old_items); # make array continuous
		return $old_items;
	}

	public static function diff($old, $new) {
		$old_items = self::GetItems($old);
		foreach ($old_items AS &$item) {
			$item = array_merge($item, Item::get($item['id'], array('name', 'version'))); # get name + version
		}

		$new_items = self::GetItems($new);
		foreach ($new_items AS &$item) {
				$item = array_merge($item, Item::get($item['id'], array('name', 'version'))); # get name + version
		}

		$diff = array();

		foreach ($new_items AS &$item) {
			$old_index = searchSubArray($old_items, array('name' => $item['name']));

			if ($old_index === NULL) {
				$diff[] = array('id' => $item['id'], 'comment' => $item['comment'], 'name' => $item['name'], 'version' => $item['version'], 'update' => UpdateType::ADD);
			} else if ($old_items[$old_index]['version'] != $item['version']) {
				$diff[] = array('id' => $item['id'], 'comment' => $item['comment'], 'name' => $item['name'], 'version' => $item['version'], 'update' => UpdateType::getUpdate($old_items[$old_index]['version'], $item['version']));
				unset($old_items[$old_index]);
			} else {
				unset($old_items[$old_index]);
			}
		}
		foreach ($old_items AS $item) {
			$diff[] = array('id' => $item['id'], 'comment' => 'Removing ' . $item['name'] . ' v' . $item['version'], 'name' => $item['name'], 'version' => $item['version'], 'update' => UpdateType::REMOVE);
		}

		return $diff;
	}

	public static function writeEntry($release, $lib, $comment) {
		$db_connection = db_ensure_connection();
		$release = mysql_real_escape_string($release, $db_connection);
		$lib = mysql_real_escape_string($lib, $db_connection);
		$comment = mysql_real_escape_string($comment, $db_connection);

		$db_query = 'INSERT INTO ' . DB_TABLE_STDLIB . ' (`release`, `lib`, `comment`) VALUES ("' . $release . '", UNHEX("' . $lib . '"), "' . $comment . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE || mysql_affected_rows() < 1) {
			throw new HttpException(500);
		}
	}
}
?>