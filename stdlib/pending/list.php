<?php
require_once('../../modules/HttpException/HttpException.php');
require_once('../../util.php');
require_once('../../Assert.php');
require_once('../StdlibPending.php');

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	$data = StdlibPending::GetAllEntries();

	if ($content_type == 'application/json') {
		$content = json_encode($data);
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<?xml version="1.0" encoding="utf-8" ?><ald:pending xmlns:ald="ald://api/stdlib/pending/list/schema/2012">';
		foreach ($data AS $entry) {
			$content .= '<ald:pending-entry ald:id="' . htmlspecialchars($entry['id'], ENT_QUOTES) . '" ald:comment="' . htmlspecialchars($entry['comment'], ENT_QUOTES) . '"/>';
		}
		$content .= '</ald:pending>';
	}

	header('HTTP/1.1 200 ' . HttpException::getStatusMessage(200));
	header('Content-Type: ' . $content_type);
	echo $content;
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, NULL, $e->getMessage()));
}
?>