<?php
	require_once("../../util.php");
	require_once("../../Assert.php");
	require_once("../../HttpException.php");
	require_once("../../UpdateType.php");
	require_once("../../semver.php");

	define('UPDATE_TYPE_PATCH', 2);
	define('UPDATE_TYPE_MINOR', 3);
	define('UPDATE_TYPE_MAJOR', 4);

	try
	{
		#Assert::RequestMethod("POST");
		Assert::GetParameters("type");
		$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");
		$type = UpdateType::getCode($_GET["type"], "stdlib_releases");

		$db_connection = db_ensure_connection();

		# get latest release
		$db_query = "SELECT `release` FROM " . DB_TABLE_STDLIB_RELEASES;
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error());
		}
		if (mysql_num_rows($db_result) < 1)
		{
			$prev_release = "0.0.0";
		}
		else
		{
			$releases = array();
			while ($release = mysql_fetch_assoc($db_result))
			{
				$releases[] = $release;
			}
			usort($releases, "semver_sort"); # sort by "release" field, following semver rules

			$db_entry = $releases[count($releases) - 1]; # latest release
			$prev_release = $db_entry["release"];
		}

		# todo: bump version number according to $type
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