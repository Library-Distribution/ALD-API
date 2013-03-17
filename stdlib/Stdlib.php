<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../sql2array.php');
require_once(dirname(__FILE__) . '/../util.php');
require_once(dirname(__FILE__) . '/StdlibPending.php');
require_once(dirname(__FILE__) . '/releases/StdlibRelease.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');

Stdlib::cleanup();

class Stdlib
{
	public static function GetItems($release)
	{
		if (StdlibRelease::exists($release, StdlibRelease::PUBLISHED_YES)) { # check if published
			$db_connection = db_ensure_connection();
			$release = mysql_real_escape_string($release, $db_connection);

			$db_query = 'SELECT HEX(`item`) AS id, comment FROM ' . DB_TABLE_STDLIB . " WHERE `release` = '$release'";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500);
			}

			return sql2array($db_result);
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

	public static function writeEntry($release, $id, $comment) {
		$db_connection = db_ensure_connection();
		$release = mysql_real_escape_string($release, $db_connection);
		$id = mysql_real_escape_string($id, $db_connection);
		$comment = mysql_real_escape_string($comment, $db_connection);

		$db_query = 'INSERT INTO ' . DB_TABLE_STDLIB . ' (`release`, `item`, `comment`) VALUES ("' . $release . '", UNHEX("' . $id . '"), "' . $comment . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE || mysql_affected_rows() < 1) {
			throw new HttpException(500);
		}
	}

	public static function releaseHasItem($release, $id) {
		$db_connection = db_ensure_connection();
		$release = mysql_real_escape_string($release, $db_connection);
		$id= mysql_real_escape_string($id, $db_connection);

		$db_query = 'SELECT * FROM ' . DB_TABLE_STDLIB . ' WHERE `release` = "' . $release . '" AND `item` = UNHEX("' . $id . '")';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		return mysql_num_rows($db_result) > 0;
	}

	public static function cleanup() {
		$db_connection = db_ensure_connection();

		# ensure not 2x stdlib with same item and release
		$db_query = 'SELECT `release`, `item` FROM ' . DB_TABLE_STDLIB . ' GROUP BY `release`, `item` HAVING COUNT(*) > 1';
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500, NULL, mysql_error());
		}

		while ($dup = mysql_fetch_assoc($db_result)) {
			$db_query = 'DELETE FROM ' . DB_TABLE_STDLIB . ' WHERE `release` = "' . $dup['release'] . '" AND `item` = "' . $dup['item'] . '" LIMIT 1';
			$db_result2 = mysql_query($db_query, $db_connection);
			if ($db_result2 === FALSE) {
				throw new HttpException(500);
			}
		}
	}
}
?>