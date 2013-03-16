<?php
require_once('../../modules/HttpException/HttpException.php');
require_once('../../util.php');
require_once('../../Assert.php');
require_once('../../User.php');
require_once('../../Item.php');
require_once('../../items/ItemType.php');
require_once('../StdlibPending.php');
require_once('../Stdlib.php');
require_once('../releases/StdlibRelease.php');
require_once('Candidate.php');
require_once('../../config/stdlib.php');

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters('id', array('name', 'version'));
	Assert::PostParameters('reason');

	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	if (!isset($_GET['id'])) {
		$item = Item::getId($_GET['name'], $_GET['version']);
	} else {
		if (!Item::existsId($_GET['id'])) {
			throw new HttpException(404);
		}
		$item = $_GET['id'];
	}

	user_basic_auth('Restricted API');

	$allowed_types = explode("\0", STDLIB_ALLOWED_TYPES);
	$t = Item::get($item, array('type'));
	if (!in_array(ItemType::getName($t['type']), $allowed_types)) {
		throw new HttpException(403, NULL, 'This type of item can not be part of the stdlib.');
	}

	# reject if pending or in latest published release (don't check for unpublished releases - equals pending)
	if (StdlibPending::IsPending($item)) {
		throw new HttpException(409, NULL, 'This item is already pending for future inclusion.');
	}

	$deletion = false;
	if (Stdlib::releaseHasItem(StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, StdlibRelease::PUBLISHED_YES), $item)) {
		if (!isset($_POST['delete']) || !in_array($_POST['delete'], array('1', 'true', 'yes'))) {
			throw new HttpException(409, NULL, 'This item is already in the stdlib.');
		}
		$deletion = true;
	}

	if (Candidate::existsItem($item)) {
		$id = Candidate::getId($item);
		$status = Candidate::accepted($id);
		if ($status === FALSE) { # previously rejected
			if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], User::PRIVILEGE_STDLIB)) { # reject unless privilege stdlib
				throw new HttpException(403, NULL, 'This item has been refused earlier. Only members of the stdlib team can make it a candidate again!');
			}
		} else if ($status === NULL) { # open
			throw new HttpException(409, NULL, 'This item is already a candidate.'); # reject
		}
		# else if ($status === TRUE) <= allow, as it isn't in the stdlib or pending anymore (must have been removed)
	}

	# create DB entry
	$candidate = Candidate::create($item, User::getId($_SERVER['PHP_AUTH_USER']), $_POST['reason'], $deletion);

	# return the ID
	if ($content_type == 'application/json') {
		$content = json_encode(array('candidate' => $candidate));
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<?xml version="1.0" encoding="utf-8" ?><ald:candidate xmlns:ald="ald://api/stdlib/candidates/create/schema/2012">' . htmlspecialchars($candidate, ENT_QUOTES) . '</ald:candidate>';
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