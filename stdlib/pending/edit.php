<?php
require_once '../../modules/HttpException/HttpException.php';
require_once '../../util.php';
require_once '../../Assert.php';
require_once '../../User.php';
require_once '../../util/Privilege.php';
require_once '../StdlibPending.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters('id');
	Assert::PostParameters('comment');

	if (!StdlibPending::IsPending($_GET['id'])) {
		throw new HttpException(404);
	}

	user_basic_auth('Only members of the stdlib team can edit pending items.');
	if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::STDLIB)) {
		throw new HttpException(403, 'Only members of the stdlib team can edit pending items.');
	}

	StdlibPending::SetComment($_GET['id'], $_POST['comment']);

	header('HTTP/1.1 204 ' . HttpException::getStatusMessage(204));
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>