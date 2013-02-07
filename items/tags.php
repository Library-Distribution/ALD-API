<?php
require_once('../HttpException.php');
require_once('../db.php');
require_once('../util.php');
require_once('../Assert.php');

try {
	Assert::RequestMethod('GET'); # only allow GET requests

	# validate accept header of request
	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	# connect to database server
	$db_connection = db_ensure_connection();

	$db_query = 'SELECT tags FROM ' . DB_TABLE_ITEMS;
	$db_result = mysql_query($db_query, $db_connection);
	if (!$db_result) {
		throw new HttpException(500);
	}

	$tags = array();
	while ($row = mysql_fetch_array($db_result)) {
		$new_tags = explode(';', $row['tags']);
		foreach ($new_tags AS $tag) {
			$tags[$tag] = true; # keep tags as keys for simplicity, value is meaningless
		}
	}
	$tags = array_keys($tags);

	if ($content_type == 'application/json') {
		$content = json_encode($tags);
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<ald:tags xmlns:ald="ald://api/items/tags/schema/2012">';
		foreach ($tags AS $tag) {
			$content .= '<ald:tag ald:name="' . $tag . '"/>';
		}
		$content .= '</ald:tags>';
	}

	header('HTTP/1.1 200 ' . HttpException::getStatusMessage(200));
	header('Content-type: ' . $content_type);
	echo $content;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, NULL, $e->getMessage()));
}
?>