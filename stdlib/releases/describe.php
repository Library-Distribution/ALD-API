<?php
	require_once("../../HttpException.php");
	require_once("../../db.php");
	require_once("../../util.php");
	require_once("../../semver.php");
	require_once("../../Assert.php");

	try
	{
		Assert::RequestMethod("GET");
		Assert::GetParameters("version");

		# validate accept header of request
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

		# connect to database server
		$db_connection = db_ensure_connection();

		$version = mysql_real_escape_string(strtolower($_GET["version"]), $db_connection);
		$special_version = in_array($version, array("latest", "first"));
		if ($special_version)
		{
			# unless auth
				$db_cond = "WHERE NOW() > date";

			$db_query = "SELECT `release` FROM " . DB_TABLE_STDLIB_RELEASES . " $db_cond ORDER BY `release` " . ($version = "latest" ? "DESC" : "ASC") . " LIMIT 1";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500);
			}
			if (mysql_num_rows($db_result) != 1)
			{
				throw new HttpException(404);
			}

			$db_entry = mysql_fetch_assoc($db_result);
			$version = $db_entry["release"];
		}

		$db_query = "SELECT * FROM " . DB_TABLE_STDLIB_RELEASES . " WHERE `release` = '$version'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}
		if (mysql_num_rows($db_result) != 1)
		{
			throw new HttpException(404);
		}

		$release = mysql_fetch_assoc($db_result);

		# handle update type
		$db_query = "SELECT name FROM " . DB_TABLE_UPDATE_TYPE . " WHERE id = '$release[update]'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}
		if (mysql_num_rows($db_result) != 1)
		{
			throw new HttpException(500, NULL, "unknown update type");
		}
		$db_entry = mysql_fetch_assoc($db_result);
		$release["update"] = $db_entry["name"];

		# get libs in the release
		$db_query = "SELECT lib FROM " . DB_TABLE_STDLIB . " WHERE `release` = '$release'";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		$release["libs"] = array();
		while ($lib = mysql_fetch_assoc($db_result))
			$release["libs"][] = $lib;

		# todo later: get frameworks in the release

		# todo: get last prerelease and last stable version
		# todo: compile changelog (since latest prerelease + since latest stable)

		if ($content_type == "application/json")
		{
			$content = json_encode($release);
		}
		else if ($content_type == "text/xml" || $content_type == "application/xml")
		{
			throw new HttpException(501);
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