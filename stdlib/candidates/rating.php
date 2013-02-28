<?php
require_once('../../modules/HttpException/HttpException.php');
require_once('../../util.php');
require_once('../../Assert.php');
require_once('../../User.php');
require_once('Candidate.php');

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST, Assert::REQUEST_METHOD_GET);

	$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
	if ($request_method == Assert::REQUEST_METHOD_POST) {
		Assert::GetParameters(array('id', 'mode'));
		Assert::PostParameters('reason');

		user_basic_auth('Restricted API');
		if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], User::PRIVILEGE_STDLIB)) {
			throw new HttpException(403, NULL, 'Only members of the stdlib team can accept or reject candidates.');
		}

		$final = isset($_POST['final']) && in_array($_POST['final'], array(1, '+1', 'true', 'yes'));
		if ($final && !User::hasPrivilege($_SERVER['PHP_AUTH_USER'], User::PRIVILEGE_STDLIB_ADMIN)) {
			throw new HttpException(403, NULL, 'Only stdlib admins can make a final decision.');
		}

		Candidate::rate($_GET['id'], User::getId($_SERVER['PHP_AUTH_USER']), $_GET['mode'] == 'accept', $_POST['reason'], $final);

	} else {
		Assert::GetParameters('id');
		# ...
	}

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, NULL, $e->getMessage()));
}
?>