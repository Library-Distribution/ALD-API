<?php
	require_once("../../util.php");
	require_once("../../Assert.php");
	require_once("../../HttpException.php");
	require_once("../../UpdateType.php");
	require_once("../../semver.php");
	require_once("../../User.php");
	require_once("StdlibRelease.php");

	define('UPDATE_TYPE_PATCH', UpdateType::getCode("patch", "stdlib_releases"));
	define('UPDATE_TYPE_MINOR', UpdateType::getCode("minor", "stdlib_releases"));
	define('UPDATE_TYPE_MAJOR', UpdateType::getCode("major", "stdlib_releases"));

	try
	{
		Assert::RequestMethod("POST");
		Assert::GetParameters("type");
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");
		$type = UpdateType::getCode($_GET["type"], "stdlib_releases");

		$base = StdlibRelease::RELEASE_BASE_ALL;
		if (!empty($_GET["base"]))
		{
			$base = strtolower($_GET["base"]);
			if (!in_array($base, array("all", "published")))
				throw new HttpException(400);
			switch ($base)
			{
				case "published":
					$base = StdlibRelease::RELEASE_BASE_PUBLISHED;
					break;
				default:
				case "all":
					$base = StdlibRelease::RELEASE_BASE_ALL;
					break;
			}
		}

		user_basic_auth("Restricted API");
		if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_DEFAULT_INCLUDE))
			throw new HttpException(403);

		$db_connection = db_ensure_connection();

		switch ($base)
		{
			case StdlibRelease::RELEASE_BASE_PUBLISHED:
				$published_only = true;
				break;
			default:
				$published_only = false;
		}

		# get latest release
		$prev_release = StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, $published_only);

		# bump version number according to $type
		$release = array();
		semver_parts($prev_release, $release);

		if ($type == UPDATE_TYPE_PATCH || $type == UPDATE_TYPE_MINOR || $type == UPDATE_TYPE_MAJOR)
		{
			unset($release["prerelease"]);
			unset($release["build"]);
			$release["patch"]++;

			if ($type == UPDATE_TYPE_MINOR || $type == UPDATE_TYPE_MAJOR)
			{
				$release["patch"] = 0;
				$release["minor"]++;

				if ($type == UPDATE_TYPE_MAJOR)
				{
					$release["minor"] = 0;
					$release["major"]++;
				}
			}
		}
		$release = semver_string($release);

		if ($base == StdlibRelease::RELEASE_BASE_PUBLISHED)
		{
			# check if (unpublished) release already exists
			if (StdlibRelease::exists($release))
			{
				throw new HttpException(409, NULL, "Release '$release' has already been created!");
			}
		}

		$data = array("release" => $release, "update" => $type);
		if (isset($_POST["version"]))
		{
			try {
				$result = semver_compare($_POST["version"], $release);
			} catch (Exception $e) {
				throw new HttpException(400, NULL, "Bad release version!"); # semver could not validate
			}
			if ($result != 1)
				throw new HttpException(400, NULL, "Bad release version!"); # version is smaller then minimum

			# check if release already exists
			if (StdlibRelease::exists($_POST["version"]))
			{
				throw new HttpException(409, NULL, "Release '$_POST[version]' has already been created!");
			}

			$data["release"] = mysql_real_escape_string($_POST["version"], $db_connection);
		}
		if (isset($_POST["date"]))
		{
			$data["date"] = mysql_real_escape_string($_POST["description"], $db_connection);
		}
		if (isset($_POST["description"]))
		{
			$data["description"] = mysql_real_escape_string($_POST["description"], $db_connection);
		}

		$db_query = "INSERT INTO " . DB_TABLE_STDLIB_RELEASES
				. " ("
				. implode(", ", array_map(function($item) { return "`$item`"; }, array_keys($data)))
				. ") VALUES ("
				. implode(", ", array_map(function($item) { return "'$item'"; }, array_values($data)))
				. ")";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error());
		}
		if (mysql_affected_rows() != 1)
		{
			throw new HttpException(500);
		}

		if ($content_type == "application/json")
		{
			$content = json_encode(array("release" => $data["release"]));
		}
		else if ($content_type == "text/xml" || $content_type == "application/xml")
		{
			$content = "<ald:version xmlns:ald=\"ald://api/stdlib/releases/create/schema/2012\">$data[release]</ald:version>";
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

	function semver_sort($a, $b)
	{
		return semver_compare($a["release"], $b["release"]);
	}
?>