<?php
require_once "../../Assert.php";
require_once "../../util.php";
require_once "../../User.php";
require_once '../../util/Privilege.php';
require_once "../../modules/HttpException/HttpException.php";
require_once "../../modules/semver/semver.php";
require_once "StdlibRelease.php";

try
{
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters("version");

	user_basic_auth("You must be part of the stdlib team!");
	if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], Privilege::STDLIB))
		throw new HttpException(403, NULL, "You must be part of the stdlib team!");

	if (!StdlibRelease::exists($_GET["version"], StdlibRelease::PUBLISHED_BOTH)) # check if release exists
		throw new HttpException(404, NULL, "Release does not exist!");

	if (StdlibRelease::exists($_GET["version"], StdlibRelease::PUBLISHED_YES)) # check if already published
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
		if (!StdlibRelease::exists($data["release"], StdlibRelease::PUBLISHED_BOTH)) # check if not already existing
			throw new HttpException(409, NULL, "Release '$data[release]' already exists!");

		$latest = StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, StdlibRelease::PUBLISHED_YES);
		if ($latest !== NULL && semver_compare($latest, $data['release']) != -1) # check if not below latest published release
			throw new HttpException(400, NULL, "Can't modify release version: Newer release $latest already published!");
	}

	# verify date
	if (isset($data["date"]))
	{
		# check stdlib admin
		if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], Privilege::STDLIB_ADMIN)) {
			throw new HttpException(403, NULL, 'Only stdlib admins can set the publication date for a release.');
		}

		# check valid date
		$date = array();
		if (!preg_match("/^(?<year>\d{4})\-(?<month>\d{2})\-(?<day>\d{2})(T(?<hour>\d{2})(:(?<min>\d{2})(:(?<sec>\d{2}))))?$/", $data["date"], $date))
		{
			throw new HttpException(400, NULL, "Invalid date format!");
		}
		if (!checkdate($date["month"], $date["day"], $date["year"]))
		{
			throw new HttpException(400, NULL, "Invalid date specified!");
		}

		# check not already over
		$datetime = new DateTime($data["date"]);
		$now = new DateTime();
		if ($datetime <= $now)
		{
			throw new HttpException(400, NULL, "Specified date already over!");
		}
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