<?php
require_once '../../modules/HttpException/HttpException.php';
require_once '../../modules/semver/semver.php';
require_once '../../util.php';
require_once '../../Assert.php';
require_once '../../User.php';
require_once '../../util/Privilege.php';
require_once '../StdlibPending.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters('id');
	Assert::PostParameters('delay', 'no-delay');

	if (!StdlibPending::IsPending($_GET['id'])) {
		throw new HttpException(404);
	}

	user_basic_auth('Only members of the stdlib team can delay pending items');
	if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::STDLIB)) {
		throw new HttpException(403, 'Only members of the stdlib team can delay pending items.');
	}

	if (isset($_POST['delay']) && !semver_validate($_POST['delay'])) {
		throw new HttpException(400, 'Must specify a valid semver version as delay!');
	}

	StdlibPending::SetDelay($_GET['id'], isset($_POST['delay']) ? $_POST['delay'] : NULL);

	http_response_code(204);
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>