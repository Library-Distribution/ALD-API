<?php
require_once('../../Assert.php');
require_once('../../modules/HttpException/HttpException.php');
require_once('../../util.php');
require_once('../../User.php');
require_once('../Suspension.php');

define('TIMESTAMP_FORMAT', 'Y-m-d H:i:s');

try {
	Suspension::clear();

	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	Assert::GetParameters('id', 'name');

	$id = isset($_GET['id']) ? $_GET['id'] : User::getID($_GET['name']);

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

	$active = true;
	if (isset($_GET['active'])) {
		if (in_array($_GET['active'], array('no', -1, 'false'))) {
			$active = false;
		} else if (in_array($_GET['active'], array('both', '0'))) {
			$active = NULL;
		}
	}

	$suspensions = Suspension::getSuspensionsById($id, $active);
	# cleanup the suspension entries
	foreach ($suspensions AS $suspension) {
		$suspension->created = $suspension->created->format(TIMESTAMP_FORMAT);
		if ($suspension->expires !== NULL) {
			$suspension->expires = $suspension->expires->format(TIMESTAMP_FORMAT);
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