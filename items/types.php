<?php
require_once '../util.php';
require_once '../Assert.php';
require_once 'ItemType.php';

try {
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

	http_response_code(200);
	header('Content-type: ' . $content_type);
	echo $content;
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>