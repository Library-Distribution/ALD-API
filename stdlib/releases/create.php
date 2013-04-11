<?php
	require_once("../../util.php");
	require_once("../../Assert.php");
	require_once("../../modules/HttpException/HttpException.php");
	require_once("../../UpdateType.php");
	require_once("../../modules/semver/semver.php");
	require_once("../../User.php");
	require_once("StdlibRelease.php");

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
		Assert::GetParameters("type");

		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

		$publish_status = StdlibRelease::PUBLISHED_BOTH;
		if (!empty($_GET["base"]))
		{
			switch (strtolower($_GET["base"]))
			{
				case StdlibRelease::RELEASE_BASE_PUBLISHED:
					$publish_status = StdlibRelease::PUBLISHED_YES;
					break;
				case StdlibRelease::RELEASE_BASE_ALL:
					$publish_status = StdlibRelease::PUBLISHED_BOTH;
					break;
				default:
					throw new HttpException(400, NULL, "Unsupported release base '$_GET[base]'!");
			}
		}

		user_basic_auth("Restricted API");
		if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_STDLIB))
			throw new HttpException(403);

		# get latest release
		$prev_release = StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, $publish_status);
		if ($prev_release === NULL) {
			$prev_release = '0.0.0';
		}

		if (isset($_POST["version"]))
		{
			try {
				$result = semver_compare($_POST["version"], $prev_release); # compare against previous release
			} catch (Exception $e) {
				throw new HttpException(400, NULL, "Bad release version!"); # semver could not validate
			}
			if ($result != 1)
				throw new HttpException(400, NULL, "Bad release version!"); # version <= previous release

			# check if release already exists
			if (StdlibRelease::exists($_POST["version"], StdlibRelease::PUBLISHED_BOTH))
			{
				throw new HttpException(409, NULL, "Release '$_POST[version]' has already been created!");
			}

			$release = $_POST["version"];
		} else {
			$type = UpdateType::getCode($_GET["type"], UpdateType::USAGE_STDLIB_RELEASES);
			$release = UpdateType::bumpVersion($prev_release, $type); # bump version number according to $type

			# check if (unpublished) release already exists (unpublished because the latest published is always >= the base for $release)
			# only check for PUBLISHED_YES as otherwise, $release must be based on the latest release anyway.
			if ($publish_status == StdlibRelease::PUBLISHED_YES && StdlibRelease::exists($release, StdlibRelease::PUBLISHED_BOTH))
			{
				throw new HttpException(409, NULL, "Release '$release' has already been created!");
			}
		}

		$date = isset($_POST['date']) ? $_POST['date'] : NULL;
		$description = isset($_POST['description']) ? $_POST['description'] : '';

		if ($date !== NULL) {
			if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_STDLIB_ADMIN)) {
				throw new HttpException(403, NULL, 'Only stdlib admins can set the publication date for a release.');
			}
		}

		StdlibRelease::create($release, $date, $description);

		if ($content_type == "application/json")
		{
			$content = json_encode(array("release" => $release));
		}
		else if ($content_type == "text/xml" || $content_type == "application/xml")
		{
			$content = "<?xml version='1.0' encoding='utf-8' ?><ald:version xmlns:ald=\"ald://api/stdlib/releases/create/schema/2012\">" . htmlspecialchars($release, ENT_QUOTES) . '</ald:version>';
		}
		header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
		header("Content-type: $content_type");
		echo $content;
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