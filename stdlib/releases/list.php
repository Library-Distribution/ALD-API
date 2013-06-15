<?php
	require_once("../../modules/HttpException/HttpException.php");
	require_once("../../util.php");
	require_once("../../User.php");
	require_once '../../util/Privilege.php';
	require_once('../../SortHelper.php');
	require_once("../../Assert.php");
	require_once("StdlibRelease.php");

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_GET);

		# validate accept header of request
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

		$publish_status = StdlibRelease::PUBLISHED_YES;
		if (!empty($_GET["published"]))
		{
			$published = strtolower($_GET["published"]);
			$both = false;

			if (in_array($published, array(-1, "no", "false"), true) || ($both = in_array($published, array(0, "both"), true))) {
				user_basic_auth("Unpublished releases can only be viewed by members of the stdlib team!"); # check auth
				if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], Privilege::STDLIB))
					throw new HttpException(403);
				$publish_status = $both ? StdlibRelease::PUBLISHED_BOTH : StdlibRelease::PUBLISHED_NO;
			}
			# else if (in_array($published, array(1, "+1", "true", "yes"))) # the default
		}

		# add sort support
		$sort_list = SortHelper::getListFromParam(isset($_GET['sort']) ? $_GET['sort'] : '');

		$releases = StdlibRelease::ListReleases($publish_status, $_GET, $sort_list);

		if ($content_type == "application/json")
		{
			$content = json_encode($releases);
		}
		else if ($content_type == "text/xml" || $content_type == "application/xml")
		{
			$content = "<?xml version='1.0' encoding='utf-8' ?><ald:releases xmlns:ald=\"ald://api/stdlib/releases/list/schema/2012\">";
			foreach ($releases AS $release)
			{
				$content .= '<ald:release ald:version="' . htmlspecialchars($release, ENT_QUOTES) . '"/>';
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