<?php
require_once '../modules/HttpException/HttpException.php';
require_once '../util.php';
require_once '../sql2array.php';
require_once '../db.php';
require_once '../Assert.php';
require_once '../SortHelper.php';
require_once '../FilterHelper.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	$db_connection = db_ensure_connection();
	$db_sort = '';
	$db_join = '';

	$filter = new FilterHelper($db_connection, DB_TABLE_STDLIB);

	$filter->add(array('name' => 'name', 'db-table' => DB_TABLE_ITEMS));
	$filter->add(array('name' => 'user', 'type' => 'binary', 'db-table' => DB_TABLE_ITEMS));
	$filter->add(array('name' => 'id', 'type' => 'binary', 'db-table' => DB_TABLE_ITEMS));

	$db_cond = $filter->evaluate($_GET, ' AND ');

	if (isset($_GET['sort'])) {
		$sort_list = SortHelper::getListFromParam($_GET['sort']);
		$db_sort = SortHelper::getOrderClause($sort_list, array('name' => '`name`', 'version' => '`position`'));
		if (array_key_exists('version', $sort_list)) {
			SortHelper::PrepareSemverSorting(DB_TABLE_ITEMS, 'version', $db_cond);
			$db_join = ' LEFT JOIN `semver_index` ON (`' . DB_TABLE_ITEMS . '`.`version` = `semver_index`.`version`) ';
		}
	}

	$db_query = 'SELECT name, `' . DB_TABLE_ITEMS . '`.`version`, HEX(`id`) AS id, GROUP_CONCAT(DISTINCT `release` SEPARATOR "\0") AS releases FROM ' . DB_TABLE_STDLIB . ', ' . DB_TABLE_ITEMS . $db_join . ' WHERE item = id ' . $db_cond . ' GROUP BY name, `' . DB_TABLE_ITEMS . '`.`version` ' . $db_sort;
	$db_result = $db_connection->query($db_query);

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
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>