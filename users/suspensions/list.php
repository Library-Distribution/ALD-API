<?php
require_once('../../Assert.php');
require_once('../../modules/HttpException/HttpException.php');
require_once('../../util.php');
require_once('../../User.php');
require_once('../Suspension.php');

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	Assert::GetParameters('id', 'name');

	$id = isset($_GET['id']) ? $_GET['id'] : User::getID($_GET['name']);
	$request_method = strtoupper($_SERVER['REQUEST_METHOD']);

	user_basic_auth('Restricted API');
	# validate: moderators and admins only
	if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], User::PRIVILEGE_USER_MANAGE) && !User::hasPrivilege($_SERVER['PHP_AUTH_USER'], User::PRIVILEGE_ADMIN)) {
		throw new HttpException(403);
	}
	if ($id == User::getID($_SERVER['PHP_AUTH_USER'])) { # cannot view own suspensions
		throw new HttpException(403);
	}

	# validate accept header of request
	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml', 'application/x-ald-package'), 'application/json');

	$suspensions = Suspension::getSuspensionsById($id, NULL);
	# cleanup the suspension entries
	foreach ($suspensions AS $suspension) {
		unset($suspension->length);
		$suspension->since = $suspension->since->format('c');
		if ($suspension->expires !== NULL) {
			$suspension->expires = $suspension->expires->format('c');
		}
	}

	if ($content_type == 'application/json') {
		$content = json_encode($suspensions);
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<ald:suspensions xmlns:ald="ald://api/users/suspend/schema/2012">';
		foreach ($suspensions AS $suspension) {
			$content .= '<ald:suspension ';
			foreach ($suspension AS $key => $val) {
				$content .= 'ald:' . $key . '="' . (is_bool($val) ? ($val ? 'true' : 'false') : $val) . '" ';
			}
			$content .= '/>';
		}
		$content .= '</ald:suspensions>';
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