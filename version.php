<?php
	define('API_VERSION', '0.1.0');

	require_once("util.php");
	require_once("Assert.php");

	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

	if ($content_type == "application/json")
	{
		$content = json_encode(array("version" => API_VERSION));
	}
	else
	{
		$content = "<?xml version='1.0' encoding='utf-8' ?><ald:version xmlns:ald='ald://api/version/schema/2012'>" . htmlspecialchars(API_VERSION, ENT_QUOTES) . "</ald:version>";
	}

	header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
	header("Content-type: $content_type");
	echo $content;
	exit;
?>