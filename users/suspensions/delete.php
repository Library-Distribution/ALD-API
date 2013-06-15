<?php
require_once '../../Assert.php';
require_once '../../modules/HttpException/HttpException.php';
require_once '../../util.php';
require_once '../../User.php';
require_once '../../util/Privilege.php';
require_once '../Suspension.php';

try {
	Suspension::clear();

	Assert::RequestMethod(Assert::REQUEST_METHOD_DELETE);
	Assert::GetParameters(array('id', 'suspension'), array('name', 'suspension'));

	$id = isset($_GET['id']) ? $_GET['id'] : User::getID($_GET['name']);
	$suspension = $_GET['suspension'];

	user_basic_auth('Restricted API');

	$suspension = Suspension::getSuspension($suspension);
	if ($suspension->user != $id) {
		throw new HttpException(400);
	}

	if ($suspension->restricted) {
		$can_delete = User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::MODERATOR) && User::getID($_SERVER['PHP_AUTH_USER']) != $id;
	} else {
		$can_delete = User::getID($_SERVER['PHP_AUTH_USER']) == $id || User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::MODERATOR);
	}

	if ($can_delete) {
		$suspension->delete();
	} else {
		throw new HttpException(403);
	}

	header('HTTP/1.1 204 ' . HttpException::getStatusMessage(204));
	exit;
} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, NULL, $e->getMessage()));
}
?>