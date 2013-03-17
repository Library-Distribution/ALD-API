<?php
	require_once('../util.php');
	require_once('../Assert.php');
	require_once('ItemType.php');

	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	$types = ItemType::getAllNames();

	if ($content_type == 'application/json')
	{
		$content = json_encode($types);
	}
	else
	{
		$content = '<?xml version="1.0" encoding="utf-8" ?><ald:types xmlns:ald="ald://api/items/types/schema/2012">' ;
		foreach ($types AS $type)
			$content .= '<ald:type ald:name="' .  htmlspecialchars($type, ENT_QUOTES) . '"/>';
		$content .= '</ald:types>';
	}

	header('HTTP/1.1 200 ' . HttpException::getStatusMessage(200));
	header('Content-type: ' . $content_type);
	echo $content;
	exit;
?>