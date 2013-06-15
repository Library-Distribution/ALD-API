<?php
require_once "../../util.php";
require_once "../../Assert.php";
require_once "../../modules/HttpException/HttpException.php";
require_once "../../User.php";
require_once '../../util/Privilege.php';
require_once "StdlibRelease.php";

try
{
	Assert::RequestMethod(Assert::REQUEST_METHOD_DELETE);
	Assert::GetParameters("version");

	user_basic_auth("Restricted API");
	if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], Privilege::STDLIB) || !User::hasPrivilege($_SERVER["PHP_AUTH_USER"], Privilege::STDLIB_ADMIN))
		throw new HttpException(403);

	StdlibRelease::delete($_GET["version"]);
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