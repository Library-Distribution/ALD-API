<?php
require_once '../../Assert.php';
require_once '../../modules/HttpException/HttpException.php';
require_once '../../util.php';
require_once '../../User.php';
require_once '../../util/Privilege.php';
require_once '../Suspension.php';

try {
	Suspension::clear();

	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters('id', 'name');
	Assert::PostParameters('reason');

	$id = isset($_GET['id']) ? $_GET['id'] : User::getID($_GET['name']);

	user_basic_auth('Restricted API');
	# validate: moderators only
	if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::MODERATOR)) {
		throw new HttpException(403);
	}
	if ($id == User::getID($_SERVER['PHP_AUTH_USER'])) { # cannot suspend self
		throw new HttpException(403);
	}

	$reason = $_POST['reason'];
	$restricted = empty($_POST['restricted']) || in_array($_POST['restricted'], array('yes', 1, '+1', 'true'));
	$expires = isset($_POST['expires']) ? $_POST['expires'] : NULL;

	Suspension::createForId($id, $reason, $expires, $restricted);

	http_response_code(204);
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>