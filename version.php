<?php
	define('API_VERSION', '0.0.0');

	require_once("util.php");
	require_once("Assert.php");

	Assert::RequestMethod("GET");
	$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

	if ($content_type == "application/json")
	{
		$content = json_encode(array("version" => API_VERSION));
	}
	else
	{
		$content = "<ald:version xmlns:ald='ald://api/version/schema/2012'>" . API_VERSION . "</ald:version>";
	}

	header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
	header("Content-type: $content_type");
	echo $content;
	exit;
?>