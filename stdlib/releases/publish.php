<?php
	require_once("../../Assert.php");
	require_once("../../util.php");
	require_once("../../modules/HttpException/HttpException.php");
	require_once("StdlibRelease.php");
	require_once("../../User.php");

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
		Assert::GetParameters("version");

		user_basic_auth("You must be part of the stdlib team!");
		if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_STDLIB))
			throw new HttpException(403, NULL, "You must be part of the stdlib team!");

		# make sure all releases that should be published are published
		StdlibRelease::publishPending();

		if (!StdlibRelease::exists($_GET["version"], StdlibRelease::PUBLISHED_BOTH)) # check if release exists
			throw new HttpException(404, NULL, "Release does not exist!");

		if (StdlibRelease::exists($_GET["version"], StdlibRelease::PUBLISHED_YES)) # check if already published
			throw new HttpException(403, NULL, "Must not change published release!");

		StdlibRelease::publish($_GET["version"]);
		header("HTTP/1.1 204 " . HttpException::getStatusMessage(204));
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