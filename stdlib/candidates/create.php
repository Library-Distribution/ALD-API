<?php
require_once('../../modules/HttpException/HttpException.php');
require_once('../../util.php');
require_once('../../Assert.php');
require_once('../../User.php');
require_once('../../Item.php');
require_once('../StdlibPending.php');
require_once('../Stdlib.php');
require_once('../releases/StdlibRelease.php');
require_once('Candidate.php');

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

	# reject if pending or in latest published release (don't check for unpublished releases - equals pending)
	if (StdlibPending::IsPending($item) || Stdlib::releaseHasItem(StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, StdlibRelease::PUBLISHED_YES), $item)) {
		throw new HttpException(409, NULL, 'This item is already in the stdlib or pending for future inclusion.');
	}

	if (Candidate::exists($item)) {
		$status = Candidate::accepted($item);
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
	$candidate = Candidate::create($item, User::getId($_SERVER['PHP_AUTH_USER']), $_POST['reason']);

	# return the ID
	if ($content_type == 'application/json') {
		$content = json_encode(array('candidate' => $candidate));
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<ald:candidate xmlns:ald="ald://api/stdlib/candidates/create/schema/2012">' . $candidate . '</ald:candidate>';
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