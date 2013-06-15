<?php
require_once "../modules/HttpException/HttpException.php";
require_once "../db.php";
require_once "../util.php";
require_once '../SortHelper.php';
require_once '../sql2array.php';
require_once "../Assert.php";
require_once "../User.php";
require_once '../util/Privilege.php';

try
{
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET); # only allow GET requests

	# validate accept header of request
	$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

	# connect to database server
	$db_connection = db_ensure_connection();

	# retrieve data limits
	$db_limit = "";
	$db_order = '';
	$db_cond = '';

	if (isset($_GET["count"]) && strtolower($_GET["count"]) != "all")
	{
		$db_limit = "LIMIT " . $db_connection->real_escape_string($_GET["count"]);
	}
	if (isset($_GET["start"]))
	{
		if (!$db_limit)
		{
			$db_limit = "LIMIT 18446744073709551615"; # Source: http://dev.mysql.com/doc/refman/5.5/en/select.html
		}
		$db_limit .= " OFFSET " .  $db_connection->real_escape_string($_GET["start"]);
	}

	if (isset($_GET['sort'])) {
		$db_order = SortHelper::getOrderClause(SortHelper::getListFromParam($_GET['sort']), array('name' => '`name`', 'joined' => '`joined`'));
	}

	# retrieve filters
	if (isset($_GET['privileges'])) {
		$privilege = Privilege::fromArray(explode(' ', $_GET['privileges']));
		if ($privilege == Privilege::NONE) {
			$db_cond .= ($db_cond ? ' AND ' : 'WHERE ') . '`privileges` = ' . $privilege;
		} else {
			$db_cond .= ($db_cond ? ' AND ' : 'WHERE ') . '(`privileges` & ' . $privilege . ') = ' . $privilege;
		}
	}

	# query for data:
	$db_query = "SELECT name, HEX(id) AS id FROM " . DB_TABLE_USERS . " $db_cond $db_order $db_limit";
	$db_result = $db_connection->query($db_query);

	# parse data to array
	$data = sql2array($db_result);

	# return content-type specific data
	if ($content_type == "application/json")
	{
		$content = json_encode($data);
	}
	else if ($content_type == "text/xml" || $content_type == "application/xml")
	{
		$content = "<?xml version='1.0' encoding='utf-8' ?><ald:user-list xmlns:ald=\"ald://api/users/list/schema/2012\">";
		foreach ($data AS $item)
		{
			$content .= '<ald:user ald:name="' . htmlspecialchars($item["name"], ENT_QUOTES) . '" ald:id="' . htmlspecialchars($item["id"], ENT_QUOTES) . '"/>';
		}
		$content .= "</ald:user-list>";
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
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>