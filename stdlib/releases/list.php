<?php
	require_once("../../HttpException.php");
	require_once("../../db.php");
	require_once("../../util.php");
	require_once("../../User.php");
	require_once("../../Assert.php");

	try
	{
		Assert::RequestMethod("GET");

		# validate accept header of request
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

		# connect to database server
		$db_connection = db_ensure_connection();

		$db_cond = " WHERE (date AND NOW() > date)";
		if (!empty($_GET["published"]))
		{
			$published = strtolower($_GET["published"]);
			if (in_array($published, array(-1, "no", "false"), true))
			{
				# check auth
				user_basic_auth("Unpublished releases can only be viewed by members of the stdlib team!");
				if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_DEFAULT_INCLUDE))
					throw new HttpException(403);
				$db_cond = " WHERE (!date OR NOW() < date)";
			}
			else if (in_array($published, array(0, "both"), true))
			{
				# check auth
				user_basic_auth("Unpublished releases can only be viewed by members of the stdlib team!");
				if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_DEFAULT_INCLUDE))
					throw new HttpException(403);
				$db_cond = "";
			}
			# else if (in_array($published, array(1, "+1", "true", "yes"))) # the default
		}

		$db_query = "SELECT `release` FROM " . DB_TABLE_STDLIB_RELEASES . $db_cond;
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error() . " - \"" . $db_query . "\"");
		}

		$releases = array();
		while ($release = mysql_fetch_assoc($db_result))
		{
			$releases[] = $release["release"];
		}

		if ($content_type == "application/json")
		{
			$content = json_encode($releases);
		}
		else if ($content_type == "text/xml" || $content_type == "application/xml")
		{
			$content = "<ald:releases xmlns:ald=\"ald://api/stdlib/releases/list/schema/2012\">";
			foreach ($releases AS $release)
			{
				$content .= "<ald:release ald:version=\"$release\"/>";
			}
			$content .= "</ald:releases>";
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