<?php
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../sql2array.php');
require_once(dirname(__FILE__) . '/../util.php');
require_once(dirname(__FILE__) . '/StdlibPending.php');
require_once(dirname(__FILE__) . '/releases/StdlibRelease.php');
require_once(dirname(__FILE__) . '/../modules/HttpException/HttpException.php');

class Stdlib
{
	public static function GetItems($release)
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

			return sql2array($db_result);
		} else {
			$old_items = Stdlib::GetItems(StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, StdlibRelease::PUBLISHED_YES));
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
	}
}
?>