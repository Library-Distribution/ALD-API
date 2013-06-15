<?php
require_once '../../modules/HttpException/HttpException.php';
require_once '../../util.php';
require_once '../../Assert.php';
require_once '../../User.php';
require_once '../../Item.php';
require_once 'Candidate.php';
require_once '../StdlibPending.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters('id');

	user_basic_auth('Restricted API');
	if (User::getId($_SERVER['PHP_AUTH_USER']) != Item::getUserForId(Candidate::getItem($_GET['id']))) {
		throw new HttpException(403, NULL, 'Only the item uploader himself can approve a candidate!');
	}

	Candidate::approve($_GET['id']);

	if (Candidate::accepted($_GET['id'])) {
		StdlibPending::AddEntry(Candidate::getItem($_GET['id']), '');
	}

	header('HTTP/1.1 204 ' . HttpException::getStatusMessage(204));
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, NULL, $e->getMessage()));
}
?>