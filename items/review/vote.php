<?php
require_once '../../util.php';
require_once '../../User.php';
require_once '../../util/Privilege.php';
require_once '../../Item.php';
require_once '../../modules/HttpException/HttpException.php';
require_once 'Review.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters(array('id', 'mode'), array('name', 'version', 'mode'));
	Assert::PostParameters('reason');

	$mode = strtolower($_GET['mode']);
	if ($mode == 'accept') {
		$accept = true;
	} else if ($mode == 'reject') {
		$accept = false;
	} else {
		throw new HttpException(400);
	}

	$final = false;
	if (isset($_POST['final'])) {
		if (in_array($_POST['final'], array('yes', 'true', '+1', '1'))) {
			$final = true;
		} else if (!in_array($_POST['final'], array('no', 'false', '-1'))) {
			throw new HttpException(400);
		}
	}

	if (!isset($_GET['id'])) {
		$id = Item::getId($_GET['name'], $_GET['version']);
	} else {
		$id = $_GET['id'];
	}

	user_basic_auth('Restricted API');
	if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::REVIEW)) {
		throw new HttpException(403, 'Only members of the stdlib team can review items');
	}

	if (Item::IsReviewed($id)) {
		throw new HttpException(403, 'A reviewed item can not be modified');
	}

	Review::AddReview($id, User::getId($_SERVER['PHP_AUTH_USER']), $accept, $_POST['reason'], $final);
	header('HTTP/1.1 204 ' . HttpException::getStatusMessage(204));
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>