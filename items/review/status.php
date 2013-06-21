<?php
require_once '../../util.php';
require_once '../../User.php';
require_once '../../modules/HttpException/HttpException.php';
require_once 'Review.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	Assert::GetParameters('id', array('name', 'version'));

	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	if (!isset($_GET['id'])) {
		$id = Item::getId($_GET['name'], $_GET['version']);
	} else {
		$id = $_GET['id'];
	}

	$reviews = Review::GetReviews($id);

	if ($content_type == 'application/json') {
		$content = json_encode($reviews);
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<?xml version="1.0" encoding="utf-8" ?><ald:item-reviews xmlns:ald="ald://api/items/review/status/schema/2012">';
		foreach ($reviews AS $review) {
			$content .= '<ald:review id="' . $review['id'] . '" user="' . $review['user'] . '" accept="' . (int)$review['accept'] . '" final="' . (int)$review['final'] . '" date="' . $review['date'] . '"/>';
		}
		$content .= '</ald:item-reviews>';
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