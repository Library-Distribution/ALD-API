<?php
	require_once("../../util.php");
	require_once("../../Assert.php");
	require_once("../../HttpException.php");
	require_once("../../User.php");

	try
	{
		Assert::RequestMethod("DELETE");
		Assert::GetParameters("version");

		user_basic_auth("Restricted API");
		if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_DEFAULT_INCLUDE))
			throw new HttpException(403);

		$db_connection = db_ensure_connection();
		$release = mysql_real_escape_string($_GET["version"], $db_connection);

		$db_query = "DELETE FROM " . DB_TABLE_STDLIB_RELEASES . " WHERE `release` = '$release' AND (!date OR NOW() < date)";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error());
		}
		else if (mysql_affected_rows($db_connection) < 1)
		{
			throw new HttpException(400, NULL, "Release doesn't exist or is already published.");
		}
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