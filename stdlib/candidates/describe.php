<?php
require_once '../../modules/HttpException/HttpException.php';
require_once '../../util.php';
require_once '../../Assert.php';
require_once 'Candidate.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	Assert::GetParameters('id');

	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	$candidate = Candidate::describe($_GET['id']);

	if ($content_type == 'application/json') {
		$content = json_encode($candidate);
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<?xml version="1.0" encoding="utf-8" ?><ald:candidate xmlns:ald="ald://api/stdlib/candidates/describe/schema/2012"';
		foreach ($candidate AS $k => $v) {
			$content .= ' ald:' . $k . '="' . htmlspecialchars($v, ENT_QUOTES) . '"';
		}
		$content .= '/>';
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