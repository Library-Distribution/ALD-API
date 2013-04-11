<?php
	require_once("../modules/HttpException/HttpException.php");
	require_once("../db.php");
	require_once("../util.php");
	require_once('../SortHelper.php');
	require_once('../util/DB/DataFilter.php');
	require_once("../User.php");
	require_once("../Assert.php");
	require_once("../modules/semver/semver.php");
	require_once('ItemType.php');
	require_once('../sql2array.php');

	# this complicated query ensures items without any ratings are considered to be rated 0
	define('SQL_QUERY_RATING', '(SELECT CASE WHEN ' . DB_TABLE_ITEMS . '.id IN (SELECT item FROM ' . DB_TABLE_RATINGS . ') THEN (SELECT ROUND(AVG(rating), 1) FROM ' . DB_TABLE_RATINGS . ' WHERE ' . DB_TABLE_RATINGS . '.item = ' . DB_TABLE_ITEMS . '.id) ELSE 0 END)');

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_GET); # only allow GET requests

		# validate accept header of request
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

		# connect to database server
		$db_connection = db_ensure_connection();

		# retrieve conditions for returned data from GET parameters
		$db_having = '';
		$db_join = '';
		$db_join_on = '';
		$db_limit = "";
		$db_order = '';

		$filter = new DataFilter(DB_TABLE_ITEMS, $db_connection);

		$filter->add(array('name' => 'type', 'type' => 'custom', 'coerce' => array('ItemType', 'getCode')));
		$filter->add(array('name' => 'user', 'type' => 'binary')); # WARN: changes parameter to receive ID instead of name
		$filter->add(array('name' => 'name'));
		$filter->add(array('name' => 'reviewed', 'type' => 'switch')); # reviewed and unreviewed items
		$filter->add(array('name' => 'stable', 'type' => 'switch', 'db-name' => 'version', 'db-function' => 'semver_stable'));

		$filter->add(array('name' => 'downloads', 'type' => 'int')); # filter for download count
		$filter->add(array('name' => 'downloads-min', 'db-name' => 'downloads', 'type' => 'int', 'operator' => '>='));
		$filter->add(array('name' => 'downloads-max', 'db-name' => 'downloads', 'type' => 'int', 'operator' => '<='));

		$filter->add(array('name' => 'tags', 'operator' => 'REGEXP', 'type' => 'custom', 'coerce' => 'coerce_regex'));
		function coerce_regex($value, $db_connection) {
			return '"(^|;)' . $db_connection->real_escape_string($value) . '($|;)"';
		}

		# special filtering (post-MySQL), thus not handled by DataFilter
		if (isset($_GET["version"]))
		{
			$version = strtolower($_GET["version"]);
			if (!in_array($version, array("latest", "first")))
			{
				throw new HttpException(400);
			}
		}

		# retrieve sorting parameters
		$sort_by_rating = $sort_by_version = false;
		if (isset($_GET['sort'])) {
			$sort_list = SortHelper::getListFromParam($_GET['sort']);
			$db_order = SortHelper::getOrderClause($sort_list, array('name' => '`name`', 'version' => '`position`', 'uploaded' => '`uploaded`', 'downloads' => '`downloads`', 'rating' => SQL_QUERY_RATING));
			$sort_by_rating = array_key_exists('rating', $sort_list);
			$sort_by_version = array_key_exists('version', $sort_list);
		}

		$semver_filters = array();
		foreach(array('version-min', 'version-max') AS $field) {
			if (isset($_GET[$field])) {
				$semver_filters[] = $_GET[$field];
			}
		}

		if ($sort_by_version || count($semver_filters) > 0) {
			$db_cond = $filter->evaluate($_GET);
			SortHelper::PrepareSemverSorting(DB_TABLE_ITEMS, 'version', $db_cond, $semver_filters);
			$db_join .=  ($db_join ? ', ' : 'LEFT JOIN (') . '`semver_index`';
			$db_join_on .= ($db_join_on ? ' AND ' : ' ON (') . '`' . DB_TABLE_ITEMS . '`.`version` = `semver_index`.`version`';
		}

		# These must defined below the call to SortHelper::PrepareSemverSorting() as it can not handle table joins
		$filter->add(array('name' => 'version-min', 'db-name' => 'position', 'db-table' => 'semver_index', 'operator' => '>=', 'type' => 'custom', 'coerce' => array('SortHelper', 'RetrieveSemverIndex')));
		$filter->add(array('name' => 'version-max', 'db-name' => 'position', 'db-table' => 'semver_index', 'operator' => '<=', 'type' => 'custom', 'coerce' => array('SortHelper', 'RetrieveSemverIndex')));
		$db_cond = $filter->evaluate($_GET); # re-evaluate to include the latest filters

		# enable rating filters if necessary (filter with HAVING instead of WHERE, not currently supported by DataFilter)
		if ($get_rating = isset($_GET['rating']) || isset($_GET['rating-min']) || isset($_GET['rating-max']) || $sort_by_rating) {
			$db_join .= ($db_join ? ', ' : 'LEFT JOIN (') . DB_TABLE_RATINGS;
			$db_join_on .= ($db_join_on ? ' AND ' : ' ON (') . 'item = id';

			if (isset($_GET['rating'])) {
				$db_having .= ($db_having) ? ' AND ' : 'HAVING ';
				$db_having .= $db_connection->real_escape_string($_GET['rating']) . ' = ' . SQL_QUERY_RATING;
			} else {
				if (isset($_GET['rating-min'])) {
					$db_having .= ($db_having) ? ' AND ' : 'HAVING ';
					$db_having .= $db_connection->real_escape_string($_GET['rating-min']) . ' <= ' . SQL_QUERY_RATING;
				}
				if (isset($_GET['rating-max'])) {
					$db_having .= ($db_having) ? ' AND ' : 'HAVING ';
					$db_having .= $db_connection->real_escape_string($_GET['rating-max']) . ' >= ' . SQL_QUERY_RATING;
				}
			}
		}

		# retrieve data limits
		if (isset($_GET["count"]) && strtolower($_GET["count"]) != "all" && !isset($version)) # if version ("latest" or "first") is set, the data is shortened after being filtered
		{
			$db_limit = "LIMIT " . $db_connection->real_escape_string($_GET["count"]);
		}
		if (isset($_GET["start"]) && !isset($version)) # if version ("latest" or "first") is set, the data is shortened after being filtered
		{
			if (!$db_limit)
			{
				$db_limit = "LIMIT 18446744073709551615"; # Source: http://dev.mysql.com/doc/refman/5.5/en/select.html
			}
			$db_limit .= " OFFSET " .  $db_connection->real_escape_string($_GET["start"]);
		}

		$db_join_on .= $db_join_on ? ')' : ''; # clause braces if necessary
		$db_join .= $db_join ? ')' : ''; # clause braces if necessary
		# query data
		$db_query = "SELECT DISTINCT " . DB_TABLE_ITEMS . ".name, HEX(" . DB_TABLE_ITEMS . ".id) AS id, " . DB_TABLE_ITEMS . '.version'
					. " FROM " . DB_TABLE_ITEMS . ' ' . $db_join . $db_join_on
					. " $db_cond $db_having $db_order $db_limit";
		$db_result = $db_connection->query($db_query);

		# parse data to array
		$data = sql2array($db_result);

		if (isset($version))
		{
			$versions = array();
			foreach ($data AS $index => $item) # go through all items and filter
			{
				$name = $item["name"];
				if (isset($versions[$name])) # a version of this item has already been processed
				{
					if (($version == "latest" && semver_compare($versions[$name], $item["version"]) == 1) || ($version == "first" && semver_compare($versions[$name], $item["version"]) == -1)) # the other version is higher/lower - delete the current item from output
					{
						unset($data[$index]);
					}
					else # the other version is lower/higher - find it in the $data array and delete it from there
					{
						$other_index = searchSubArray($data, array("name" => $name, "version" => $versions[$name]));
						unset($data[$other_index]);
						$versions[$name] = $item["version"]; # indicate this version as the latest / oldest being processed
					}
				}
				else # no version has yet been processed, indicate this one as first
					$versions[$name] = $item["version"];
			}
			sort($data); # sort to have a continuing index

			# shorten data as specified by parameters
			$offset = 0;
			if (isset($_GET["start"]))
			{
				$offset = $_GET["start"];
			}
			if (isset($_GET["count"]) && strtolower($_GET["count"]) != "all")
			{
				$data = array_slice($data, $offset, $_GET["count"]);
			}
			else
			{
				$data = array_slice($data, $offset);
			}
		}

		# return content-type specific data
		if ($content_type == "application/json")
		{
			$content = json_encode($data);
		}
		else if ($content_type == "text/xml" || $content_type == "application/xml")
		{
			$content = "<?xml version='1.0' encoding='utf-8' ?><ald:item-list xmlns:ald=\"ald://api/items/list/schema/2012\">";
			foreach ($data AS $item)
			{
				$content .= '<ald:item ald:name="' . htmlspecialchars($item['name'], ENT_QUOTES) . '" ald:version="' . htmlspecialchars($item['version'], ENT_QUOTES) . '" ald:id="' . htmlspecialchars($item['id'], ENT_QUOTES) . '"/>';
			}
			$content .= "</ald:item-list>";
		}

		header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
		header("Content-type: $content_type");
		echo $content;
		exit;
	}
	catch (HttpException $e)
	{
		handleHttpException($e);
	}
	catch (Exception $e)
	{
		handleHttpException(new HttpException(500, NULL, $e->getMessage()));
	}
?>
