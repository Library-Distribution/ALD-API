<?php
require_once '../modules/HttpException/HttpException.php';
require_once '../db.php';
require_once '../util.php';
require_once '../Assert.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET); # only allow GET requests

	# validate accept header of request
	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	# connect to database server
	$db_connection = db_ensure_connection();

	$db_query = 'SELECT DISTINCT tags FROM ' . DB_TABLE_ITEMS;
	$db_result = $db_connection->query($db_query);

	$tags = array();
	while ($row = $db_result->fetch_assoc()) {
		$tags = array_merge($tags, explode(';', $row['tags']));
	}
	# make tags unique
	$tags = array_unique($tags);
	# DISTINCT in the SQL query does not eliminate the need for this,
	#    as it only ensures the uniqueness of a tag-combination,
	#    not the tags themselves. It only makes the loop run fewer times.

	# ensure continous index
	sort($tags);

	if ($content_type == 'application/json') {
		$content = json_encode($tags);
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<?xml version="1.0" encoding="utf-8" ?><ald:tags xmlns:ald="ald://api/items/tags/schema/2012">';
		foreach ($tags AS $tag) {
			$content .= '<ald:tag ald:name="' . htmlspecialchars($tag, ENT_QUOTES) . '"/>';
		}
		$content .= '</ald:tags>';
	}

	http_response_code(200);
	header('Content-type: ' . $content_type);
	echo $content;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>