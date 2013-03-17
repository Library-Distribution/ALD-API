<?php
	require_once("../modules/HttpException/HttpException.php");
	require_once("../db.php");
	require_once("../util.php");
	require_once('../sort_get_order_clause.php');
	require_once("../User.php");
	require_once("../Assert.php");
	require_once("../modules/semver/semver.php");
	require_once('ItemType.php');
	require_once('../sql2array.php');

	# this complicated query ensures items without any ratings are considered to be rated 0
	define('SQL_QUERY_RATING', '(SELECT CASE WHEN ' . DB_TABLE_ITEMS . '.id IN (SELECT item FROM ratings) THEN (SELECT SUM(rating) FROM ratings WHERE ratings.item = ' . DB_TABLE_ITEMS . '.id) ELSE 0 END)');

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_GET); # only allow GET requests

		# validate accept header of request
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

		# connect to database server
		$db_connection = db_ensure_connection();

		# retrieve conditions for returned data from GET parameters
		$db_cond = "";
		$db_having = '';
		$db_join = '';
		$db_limit = "";
		$db_order = '';

		if (isset($_GET["type"]))
		{
			$db_cond = ($db_cond ? ' AND ' : 'WHERE '). "type = '" . ItemType::getCode($_GET["type"]) . "'";
		}
		if (isset($_GET["user"]))
		{
			$db_cond .= ($db_cond ? ' AND ' : 'WHERE '). "user = UNHEX('" . User::getID($_GET["user"]) . "')";
		}
		if (isset($_GET["name"]))
		{
			$db_cond .= ($db_cond ? ' AND ' : 'WHERE ') . DB_TABLE_ITEMS . ".name = '" . mysql_real_escape_string($_GET["name"], $db_connection) . "'";
		}
		if (isset($_GET["tags"]))
		{
			$db_cond .= ($db_cond ? ' AND ' : 'WHERE '). "tags REGEXP '(^|;)" . mysql_real_escape_string($_GET["tags"], $db_connection) . "($|;)'";
		}

		# reviewed and unreviewed items
		# ================================ #
		if (isset($_GET["reviewed"]) && in_array(strtolower($_GET["reviewed"]), array("no", "false", "-1")))
		{
			$db_cond .= ($db_cond ? ' AND ' : 'WHERE '). "reviewed = '0'";
		}
		else if (isset($_GET["reviewed"]) && in_array(strtolower($_GET["reviewed"]), array("both", "0")))
		{
			$db_cond .= ($db_cond ? ' AND ' : 'WHERE '). "(reviewed = '0' OR reviewed = '1')";
		}
		else # default (use "yes", "true", "+1" or "1")
		{
			$db_cond .= ($db_cond ? ' AND ' : 'WHERE '). " reviewed = '1'";
		}
		# ================================ #

		# filter for download count
		if (isset($_GET['downloads'])) {
			$db_cond .= ($db_cond ? ' AND ' : 'WHERE '). '`downloads` = ' . (int)mysql_real_escape_string($_GET['downloads']);
		} else {
			if (isset($_GET['downloads-min'])) {
				$db_cond .= ($db_cond ? ' AND ' : 'WHERE '). '`downloads` >= ' . (int)mysql_real_escape_string($_GET['downloads-min']);
			}
			if (isset($_GET['downloads-max'])) {
				$db_cond .= ($db_cond ? ' AND ' : 'WHERE '). '`downloads` <= ' . (int)mysql_real_escape_string($_GET['downloads-max']);
			}
		}

		if (isset($_GET["version"]))
		{
			$version = strtolower($_GET["version"]);
			if (!in_array($version, array("latest", "first")))
			{
				throw new HttpException(400);
			}
		}

		# retrieve sorting parameters
		$sort_by_rating = false;
		if (isset($_GET['sort'])) {
			$db_order = sort_get_order_clause($_GET['sort'], array('name' => '`name`', 'version' => '`version`', 'uploaded' => '`uploaded`', 'downloads' => '`downloads`', 'rating' => SQL_QUERY_RATING));
			$sort_by_rating = preg_match('/(^|\s)!?rating/', $_GET['sort']) == 1;
		}

		# enable rating filters if necessary
		if ($get_rating = isset($_GET['rating']) || isset($_GET['rating-min']) || isset($_GET['rating-max']) || $sort_by_rating) {
			$db_join = 'LEFT JOIN ' . DB_TABLE_RATINGS . ' ON item = id';

			if (isset($_GET['rating'])) {
				$db_having .= ($db_having) ? ' AND ' : 'HAVING ';
				$db_having .= mysql_real_escape_string($_GET['rating'], $db_connection) . ' = ' . SQL_QUERY_RATING;
			} else {
				if (isset($_GET['rating-min'])) {
					$db_having .= ($db_having) ? ' AND ' : 'HAVING ';
					$db_having .= mysql_real_escape_string($_GET['rating-min'], $db_connection) . ' <= ' . SQL_QUERY_RATING;
				}
				if (isset($_GET['rating-max'])) {
					$db_having .= ($db_having) ? ' AND ' : 'HAVING ';
					$db_having .= mysql_real_escape_string($_GET['rating-max'], $db_connection) . ' >= ' . SQL_QUERY_RATING;
				}
			}
		}

		# retrieve data limits
		if (isset($_GET["count"]) && strtolower($_GET["count"]) != "all" && !isset($version)) # if version ("latest" or "first") is set, the data is shortened after being filtered
		{
			$db_limit = "LIMIT " . mysql_real_escape_string($_GET["count"], $db_connection);
		}
		if (isset($_GET["start"]) && !isset($version)) # if version ("latest" or "first") is set, the data is shortened after being filtered
		{
			if (!$db_limit)
			{
				$db_limit = "LIMIT 18446744073709551615"; # Source: http://dev.mysql.com/doc/refman/5.5/en/select.html
			}
			$db_limit .= " OFFSET " .  mysql_real_escape_string($_GET["start"], $db_connection);
		}

		# query data
		$db_query = "SELECT DISTINCT " . DB_TABLE_ITEMS . ".name, HEX(" . DB_TABLE_ITEMS . ".id) AS id, version"
					. " FROM " . DB_TABLE_ITEMS . ' ' . $db_join
					. " $db_cond $db_having $db_order $db_limit";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

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
