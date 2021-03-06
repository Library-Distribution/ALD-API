<?php
require_once "../../Assert.php";
require_once "../../util.php";
require_once "../../modules/HttpException/HttpException.php";
require_once "StdlibRelease.php";
require_once "../../User.php";
require_once '../../util/Privilege.php';

try
{
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters("version");

	user_basic_auth("You must be part of the stdlib team!");
	if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], Privilege::STDLIB) || !User::hasPrivilege($_SERVER["PHP_AUTH_USER"], Privilege::STDLIB_ADMIN))
		throw new HttpException(403, "You must be stdlib admin to publish a release!");

	if (!StdlibRelease::exists($_GET["version"], StdlibRelease::PUBLISHED_BOTH)) # check if release exists
		throw new HttpException(404, "Release does not exist!");

	if (StdlibRelease::exists($_GET["version"], StdlibRelease::PUBLISHED_YES)) # check if already published
		throw new HttpException(403, "Must not change published release!");

	StdlibRelease::publish($_GET["version"]);
	http_response_code(204);
}
catch (HttpException $e)
{
	handleHttpException($e);
}
catch (Exception $e)
{
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>