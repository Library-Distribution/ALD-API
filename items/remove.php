<?php
	require_once("../modules/HttpException/HttpException.php");
	require_once("../db.php");
	require_once("../util.php");
	require_once("../Assert.php");

	try
	{
		Assert::RequestMethod("DELETE"); # only allow DELETE requests
		Assert::GetParameters("id");

		# authentication
		user_basic_auth("Restricted API");
		$user = $_SERVER["PHP_AUTH_USER"];

		throw new HttpException(501); # not implemented
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