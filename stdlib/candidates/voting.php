<?php
require_once '../../modules/HttpException/HttpException.php';
require_once '../../util.php';
require_once '../../Assert.php';
require_once '../../SortHelper.php';
require_once '../../User.php';
require_once '../../util/Privilege.php';
require_once 'Candidate.php';
require_once '../StdlibPending.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST, Assert::REQUEST_METHOD_GET);

	$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
	if ($request_method == Assert::REQUEST_METHOD_POST) {
		Assert::GetParameters(array('id', 'mode'));
		Assert::PostParameters('reason');

		user_basic_auth('Restricted API');
		if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::STDLIB)) {
			throw new HttpException(403, 'Only members of the stdlib team can accept or reject candidates.');
		}

		$final = isset($_POST['final']) && in_array($_POST['final'], array(1, '+1', 'true', 'yes'));
		if ($final && !User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::STDLIB_ADMIN)) {
			throw new HttpException(403, 'Only stdlib admins can make a final decision.');
		}

		# reject if same user already voted
		if (Candidate::hasVoted($_GET['id'], User::getId($_SERVER['PHP_AUTH_USER']))) {
			throw new HttpException(403, 'You cannot vote the same candidate twice.');
		}

		# reject if already closed
		if (Candidate::accepted($_GET['id']) != NULL) {
			throw new HttpException(403, 'Cannot vote a candidate that has already been accepted or rejected.');
		}

		Candidate::vote($_GET['id'], User::getId($_SERVER['PHP_AUTH_USER']), $_GET['mode'] == 'accept', $_POST['reason'], $final);

		if (Candidate::accepted($_GET['id']) && Candidate::isApproved($_GET['id'])) {
			StdlibPending::AddEntry(Candidate::getItem($_GET['id']), '');
		}

		header('HTTP/1.1 204 ' . HttpException::getStatusMessage(204));
		exit;

	} else {
		Assert::GetParameters('id');
		$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

		$filters = FilterHelper::FromParams(array('user', 'final', 'accept', 'voted', 'voted-after', 'voted-before'));
		$sort_list = SortHelper::getListFromParam(isset($_GET['sort']) ? $_GET['sort'] : '');

		$votings = Candidate::listVotings($_GET['id'], $filters, $sort_list);
		if ($content_type == 'application/json') {
			$content = json_encode($votings);
		} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
			$content = '<?xml version="1.0" encoding="utf-8" ?><ald:votings xmlns:ald="ald://api/stdlib/candidates/voting/schema/2012">';
			foreach ($votings AS $voting) {
				$content .= '<ald:voting ald:candidate="' . htmlspecialchars($voting['candidate'], ENT_QUOTES) . '" ald:user="' . htmlspecialchars($voting['user'], ENT_QUOTES) . '" ald:accept="' . htmlspecialchars($voting['accept'] ? 'true' : 'false', ENT_QUOTES) . '" ald:final="' . htmlspecialchars($voting['final'] ? 'true' : 'false', ENT_QUOTES) . '" ald:reason="' . htmlspecialchars($voting['reason'], ENT_QUOTES) . '" ald:date="' . htmlspecialchars($voting['date'], ENT_QUOTES) . '"/>';
			}
			$content .= '</ald:votings>';
		}
		header('HTTP/1.1 200 ' . HttpException::getStatusMessage(200));
		header('Content-type: ' . $content_type);
		echo $content;
		exit;
	}

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>