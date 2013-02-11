<?php
	require_once("../HttpException.php");
	require_once("../db.php");
	require_once("../util.php");
	require_once('../sql2array.php');
	require_once("../Assert.php");

	try
	{
		Assert::RequestMethod("GET"); # only allow GET requests

		# validate accept header of request
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

		# connect to database server
		$db_connection = db_ensure_connection();

		# retrieve data limits
		$db_limit = "";
		if (isset($_GET["count"]) && strtolower($_GET["count"]) != "all")
		{
			$db_limit = "LIMIT " . mysql_real_escape_string($_GET["count"], $db_connection);
		}
		if (isset($_GET["start"]))
		{
			if (!$db_limit)
			{
				$db_limit = "LIMIT 18446744073709551615"; # Source: http://dev.mysql.com/doc/refman/5.5/en/select.html
			}
			$db_limit .= " OFFSET " .  mysql_real_escape_string($_GET["start"], $db_connection);
		}

		# query for data:
		$db_query = "SELECT name, HEX(id) AS id FROM " . DB_TABLE_USERS . " $db_limit";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		# parse data to array
		$data = sql2array($db_result);

		# return content-type specific data
		if ($content_type == "application/json")
		{
			$content = json_encode($data);
		}
		else if ($content_type == "text/xml" || $content_type == "application/xml")
		{
			$content = "<ald:user-list xmlns:ald=\"ald://api/users/list/schema/2012\">";
			foreach ($data AS $item)
			{
				$content .= "<ald:user ald:name=\"{$item["name"]}\" ald:id=\"{$item["id"]}\"/>";
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
		handleHttpException(new HttpException(500, NULL, $e->getMessage()));
	}
?>