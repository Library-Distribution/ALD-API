<?php
	require_once("../../HttpException.php");
	require_once("../../db.php");
	require_once("../../util.php");
	require_once("../../semver.php");
	require_once("../../Assert.php");
	require_once("StdlibRelease.php");
	require_once("../../UpdateType.php");

	try
	{
		Assert::RequestMethod("GET");
		Assert::GetParameters("version");

		# validate accept header of request
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

		# connect to database server
		$db_connection = db_ensure_connection();

		# todo!
		$published_only = true;

		$release = StdlibRelease::describe($_GET["version"], $published_only);

		# handle update type
		$release["update"] = UpdateType::getName($release["update"]);

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