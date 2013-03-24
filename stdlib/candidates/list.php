<?php
require_once('../../modules/HttpException/HttpException.php');
require_once('../../util.php');
require_once('../../Assert.php');
require_once('../../SortHelper.php');
require_once('Candidate.php');

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);

	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	$filters = array_intersect_key($_GET, array_flip(array('user', 'item', 'created', 'created-after', 'created-before', 'approved', 'owner')));
	if (isset($_GET['sort'])) {
		$sort_list = SortHelper::getListFromParam($_GET['sort']);
	}
	$candidates = Candidate::listCandidates($filters, isset($sort_list) ? $sort_list : array());

	$filtered_candidates = array();
	$status_map = array('accepted' => true, 'open' => NULL, 'rejected' => false);

	foreach ($candidates AS $candidate) {
		if (isset($_GET['status'])) {
			if (!in_array($_GET['status'], array_keys($status_map))) {
				throw new HttpException(400);
			}

			$status = Candidate::accepted($candidate['id']);
			if ($status_map[$_GET['status']] !== $status) {
				continue;
			}
		}

		if (isset($_GET['accepted-by']) || isset($_GET['rejected-by'])) {
			$user = isset($_GET['accepted-by']) ? $_GET['accepted-by'] : $_GET['rejected-by'];
			if (!Candidate::hasVoted($candidate['id'], $user)) {
				continue;
			}

			$votings = Candidate::listVotings($candidate['id']);
			if (searchSubArray($votings, array('user' => $user, 'accept' => isset($_GET['accepted-by']))) === NULL) {
				continue;
			}
		}

		if (isset($_GET['accepted']) || isset($_GET['accepted-min']) || isset($_GET['accepted-max']) || isset($_GET['rejected']) || isset($_GET['rejected-min']) || isset($_GET['rejected-max'])) {
			$votings = Candidate::listVotings($candidate['id']);
			$count = count($votings);

			if (isset($_GET['accepted']) && $count != (int)$_GET['accepted']
				|| isset($_GET['accepted-min']) && $count < (int)$_GET['accepted-min']
				|| isset($_GET['accepted-max']) && $count > (int)$_GET['accepted-max']
				|| isset($_GET['rejected']) && $count != (int)$_GET['rejected']
				|| isset($_GET['rejected-min']) && $count < (int)$_GET['rejected-min']
				|| isset($_GET['rejected-max']) && $count > (int)$_GET['rejected-max']) {
				continue;
			}
		}

		$filtered_candidates[] = $candidate;
	}
	$candidates = $filtered_candidates;

	if ($content_type == 'application/json') {
		$content = json_encode($candidates);
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<?xml version="1.0" encoding="utf-8" ?><ald:candidates xmlns:ald="ald://api/stdlib/candidates/list/schema/2012">';
		foreach ($candidates AS $candidate) {
			$content .= '<ald:candidate ald:item="' . htmlspecialchars($candidate['item'], ENT_QUOTES) . '" ald:id="' . htmlspecialchars($candidate['id'], ENT_QUOTES) . '"/>';
		}
		$content .= '</ald:candidates>';
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