<?php
require_once('../modules/HttpException/HttpException.php');
require_once('../util.php');
require_once('../sql2array.php');
require_once('../db.php');
require_once('../Assert.php');

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	$db_connection = db_ensure_connection();
	$db_cond = '';

	if (isset($_GET['name'])) {
		$db_cond .= 'AND name = "' . mysql_real_escape_string($_GET['name'], $db_connection) . '"';
	}
	if (isset($_GET['user'])) {
		$db_cond .= 'AND user = UNHEX("' . mysql_real_escape_string($_GET['user'], $db_connection) . '")';
	}
	if (isset($_GET['id'])) {
		$db_cond .= 'AND id = UNHEX("' . mysql_real_escape_string($_GET['id'], $db_connection) . '")';
	}

	$db_query = 'SELECT name, version, HEX(`id`) AS id, GROUP_CONCAT(DISTINCT `release` SEPARATOR "\0") AS releases FROM ' . DB_TABLE_STDLIB . ', ' . DB_TABLE_ITEMS . ' WHERE item = id ' . $db_cond . ' GROUP BY name, version';
	$db_result = mysql_query($db_query, $db_connection);
	if ($db_result === FALSE) {
		throw new HttpException(500, NULL, mysql_error());
	}

	$data = sql2array($db_result, create_function('$item', '$item["releases"] = explode("\0", $item["releases"]); return $item;'));

	$items = array();
	foreach ($data AS $entry) {
		$name = $entry['name'];
		unset($entry['name']);

		if (!isset($items[$name])) {
			$items[$name] = array();
		}
		$items[$name][] = $entry;
	}

	if ($content_type == 'application/json') {
		$content = json_encode($items);
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<?xml version="1.0" encoding="utf-8" ?><ald:stdlib xmlns:ald="ald://api/stdlib/items/schema/2012">';
		foreach ($items AS $name => $versions) {
			$content .= '<ald:item ald:name="' . htmlspecialchars($name, ENT_QUOTES) . '">';
			foreach ($versions AS $version) {
				$content .= '<ald:version ald:version="' . htmlspecialchars($version['version'], ENT_QUOTES) . '" ald:id="' . htmlspecialchars($version['id'], ENT_QUOTES) . '">';
				foreach ($version['releases'] AS $release) {
					$content .= '<ald:release>' . htmlspecialchars($release, ENT_QUOTES) . '</ald:release>';
				}
				$content .= '</ald:version>';
			}
			$content .= '</ald:item>';
		}
		$content .= '</ald:stdlib>';
	}

	header('HTTP/1.1 200 ' . HttpException::getStatusMessage(200));
	header('Content-type: ' . $content_type);
	echo $content;
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, NULL, $e->getMessage()));
}
?>