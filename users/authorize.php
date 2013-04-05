<?php
require_once('../modules/HttpException/HttpException.php');
require_once('../util.php');
require_once('../User.php');
require_once('../Assert.php');

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters('id', 'name');
	Assert::PostParameters('privilege');

	user_basic_auth('Restricted API');

	if (!isset($_GET['mode']) || !in_array($_GET['mode'], array('authorize', 'unauthorize'))) {
		throw new HttpException(400);
	}
	$id = isset($_GET['name']) ? User::getID($_GET['name']) : $_GET['id'];
	$privilege = User::privilegeFromArray(array($_POST['privilege']));

	if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], User::adminPrivilegeForPrivilege($privilege))) {
		throw new HttpException(403);
	}

	if ($_GET['mode'] == 'authorize') {
		User::addPrivilegeById($id, $privilege);
	} else {
		User::removePrivilegeById($id, $privilege);
	}

	header('HTTP/1.1 204 ' . HttpException::getStatusMessage(204));
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, NULL, $e->getMessage()));
}
?>