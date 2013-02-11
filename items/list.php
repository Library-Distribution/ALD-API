<?php
	require_once("../HttpException.php");
	require_once("../db.php");
	require_once("../util.php");
	require_once("../User.php");
	require_once("../Assert.php");
	require_once("../semver.php");
	require_once('ItemType.php');
	require_once('../sql2array.php');

	try
	{
		Assert::RequestMethod("GET"); # only allow GET requests

		# validate accept header of request
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

		# connect to database server
		$db_connection = db_ensure_connection();

		# retrieve conditions for returned data from GET parameters
		$db_cond = "";
		$db_having = '';
		$db_join = '';
		$db_limit = "";

		if (isset($_GET["type"]))
		{
			$db_cond = "AND type = '" . ItemType::getCode($_GET["type"]) . "'";
		}
		if (isset($_GET["user"]))
		{
			$db_cond .= " AND user = UNHEX('" . User::getID($_GET["user"]) . "')";
		}
		if (isset($_GET["name"]))
		{
			$db_cond .= " AND " . DB_TABLE_ITEMS . ".name = '" . mysql_real_escape_string($_GET["name"], $db_connection) . "'";
		}
		if (isset($_GET["tags"]))
		{
			$db_cond .= " AND tags REGEXP '(^|;)" . mysql_real_escape_string($_GET["tags"], $db_connection) . "($|;)'";
		}

		# items in or not in the stdlib
		# ================================ #
		if (isset($_GET["stdlib"]) && in_array(strtolower($_GET["stdlib"]), array("no", "false", "-1")))
		{
			$db_cond .= " AND default_include = '0'";
		}
		else if (isset($_GET["stdlib"]) && in_array(strtolower($_GET["stdlib"]), array("yes", "true", "+1", "1")))
		{
			$db_cond .= " AND default_include = '1'";
		}
		/* else {} */ # default (use "both" or "0") - leave empty so both match
		# ================================ #

		# reviewed and unreviewed items
		# ================================ #
		if (isset($_GET["reviewed"]) && in_array(strtolower($_GET["reviewed"]), array("no", "false", "-1")))
		{
			$db_cond .= " AND reviewed = '0'";
		}
		else if (isset($_GET["reviewed"]) && in_array(strtolower($_GET["reviewed"]), array("both", "0")))
		{
			$db_cond .= " AND (reviewed = '0' OR reviewed = '1')";
		}
		else # default (use "yes", "true", "+1" or "1")
		{
			$db_cond .= " AND reviewed = '1'";
		}
		# ================================ #

		# filter for download count
		if (isset($_GET['downloads'])) {
			$db_cond .= ' AND `downloads` = ' . (int)mysql_real_escape_string($_GET['downloads']);
		} else {
			if (isset($_GET['downloads-min'])) {
				$db_cond .= ' AND `downloads` >= ' . (int)mysql_real_escape_string($_GET['downloads-min']);
			}
			if (isset($_GET['downloads-max'])) {
				$db_cond .= ' AND `downloads` <= ' . (int)mysql_real_escape_string($_GET['downloads-max']);
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

		# enable rating filters if necessary
		if ($get_rating = isset($_GET['rating']) || isset($_GET['rating-min']) || isset($_GET['rating-max'])) {
			$db_join = 'LEFT JOIN ' . DB_TABLE_RATINGS . ' ON item = id';

			# this complicated query ensures items without any ratings are considered to be rated 0
			$sub_query = '(SELECT CASE WHEN ' . DB_TABLE_ITEMS . '.id IN (SELECT item FROM ratings) THEN (SELECT SUM(rating) FROM ratings WHERE ratings.item = ' . DB_TABLE_ITEMS . '.id) ELSE 0 END)';
			if (isset($_GET['rating'])) {
				$db_having .= ($db_having) ? ' AND ' : 'HAVING ';
				$db_having .= mysql_real_escape_string($_GET['rating'], $db_connection) . ' = ' . $sub_query;
			} else {
				if (isset($_GET['rating-min'])) {
					$db_having .= ($db_having) ? ' AND ' : 'HAVING ';
					$db_having .= mysql_real_escape_string($_GET['rating-min'], $db_connection) . ' <= ' . $sub_query;
				}
				if (isset($_GET['rating-max'])) {
					$db_having .= ($db_having) ? ' AND ' : 'HAVING ';
					$db_having .= mysql_real_escape_string($_GET['rating-max'], $db_connection) . ' >= ' . $sub_query;
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
		$db_query = "SELECT DISTINCT " . DB_TABLE_ITEMS . ".name, type, HEX(" . DB_TABLE_ITEMS . ".id) AS id, version, HEX(" . DB_TABLE_ITEMS . ".user) AS userID, " . DB_TABLE_USERS . ".name AS userName"
					. " FROM " . DB_TABLE_ITEMS . ' ' . $db_join . ', ' . DB_TABLE_USERS
					. " WHERE " . DB_TABLE_ITEMS . ".user = " . DB_TABLE_USERS . ".id $db_cond $db_having $db_limit";

		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error());
		}

		# parse data to array
		$data = sql2array($db_result, 'cleanup_item');

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
			$content = "<ald:item-list xmlns:ald=\"ald://api/items/list/schema/2012\">";
			foreach ($data AS $item)
			{
				$content .= "<ald:item ald:name=\"{$item['name']}\" ald:version=\"{$item['version']}\" ald:id=\"{$item['id']}\" ald:user-id=\"{$item['user']['id']}\" ald:user=\"{$item['user']['name']}\"/>";
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
<?php
function cleanup_item($item) {
	$item["user"] = array("name" => $item["userName"], "id" => $item["userID"]);
	unset($item["userName"]);
	unset($item["userID"]);

	$item['type'] = ItemType::getName($item['type']);
	return $item;
}
?>
