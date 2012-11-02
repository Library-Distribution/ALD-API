<?php
	require_once("../../Assert.php");
	require_once("../../util.php");
	require_once("../../User.php");
	require_once("../../HttpException.php");
	require_once("../../semver.php");
	require_once("StdlibRelease.php");

	try
	{
		Assert::RequestMethod("POST");
		Assert::GetParameters("version");

		user_basic_auth("You must be part of the stdlib team!");
		if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_DEFAULT_INCLUDE))
			throw new HttpException(403, NULL, "You must be part of the stdlib team!");

		if (!StdlibRelease::exists($_GET["version"])) # check if release exists
			throw new HttpException(404, NULL, "Release does not exist!");

		if (StdlibRelease::exists($_GET["version"], true)) # check if already published
			throw new HttpException(403, NULL, "Must not change published release!");

		$data = array();
		foreach (array("version" => "release", "description" => "description", "date" => "date") AS $key => $col)
		{
			if (!empty($_POST[$key]))
				$data[$col] = $_POST[$key];
		}

		# verify release
		if (isset($data["release"]))
		{
			if (!semver_validate($data["release"])) # check if valid semver
				throw new HttpException(400, NULL, "Incorrect release version!");
			if (!StdlibRelease::exists($data["release"])) # check if not already existing
				throw new HttpException(409, NULL, "Release '$data[release]' already exists!");
		}

		# verify date
		if (isset($data["date"]))
		{
			# todo: check stdlib admin
			# check valid date
			# check not already over
		}

		if (count($data) > 0)
			StdlibRelease::update($_GET["version"], $data);

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