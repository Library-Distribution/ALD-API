<?php
require_once('../../modules/HttpException/HttpException.php');
require_once('../../util.php');
require_once('../../Assert.php');
require_once('Candidate.php');

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);

	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	$candidates = Candidate::listCandidates();

	if ($content_type == 'application/json') {
		$content = json_encode($candidates);
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<ald:candidates xmlns:ald="ald://api/stdlib/candidates/list/schema/2012">';
		foreach ($candidates AS $candidate) {
			$content .= '<ald:candidate ald:item="' . $candidate['item'] . '" ald:id="' . $candidate['id'] . '"/>';
		}
		$content .= '</ald:candidates>';
	}
	header('HTTP/1.1 200 ' . HttpException::getStatusMessage(200));
	header('Content-type: ' . $content_type);
	echo $content;
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, NULL, $e->getMessage()));
}
?>