<?php
	require_once("../../HttpException.php");
	require_once("../../util.php");
	require_once("../../User.php");
	require_once("../../Assert.php");
	require_once("StdlibRelease.php");

	try
	{
		Assert::RequestMethod("GET");

		# validate accept header of request
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

		$publish_status = StdlibRelease::PUBLISHED_YES;
		if (!empty($_GET["published"]))
		{
			$published = strtolower($_GET["published"]);
			if (in_array($published, array(-1, "no", "false"), true))
			{
				# check auth
				user_basic_auth("Unpublished releases can only be viewed by members of the stdlib team!");
				if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_DEFAULT_INCLUDE))
					throw new HttpException(403);
				$publish_status = StdlibRelease::PUBLISHED_NO;
			}
			else if (in_array($published, array(0, "both"), true))
			{
				# check auth
				user_basic_auth("Unpublished releases can only be viewed by members of the stdlib team!");
				if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_DEFAULT_INCLUDE))
					throw new HttpException(403);
				$publish_status = StdlibRelease::PUBLISHED_BOTH;
			}
			# else if (in_array($published, array(1, "+1", "true", "yes"))) # the default
		}

		$releases = StdlibRelease::ListReleases($publish_status);

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