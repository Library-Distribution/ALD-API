<?php
require_once '../../modules/HttpException/HttpException.php';
require_once '../../util.php';
require_once '../../Assert.php';
require_once '../../UpdateType.php';
require_once '../StdlibPending.php';
require_once '../releases/StdlibRelease.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	$content_type = get_preferred_mimetype(array('application/json', 'text/xml', 'application/xml'), 'application/json');

	if (isset($_GET['action'])) {
		$action = UpdateType::getCode($_GET['action'], UpdateType::USAGE_STDLIB);
	}

	$release_update = UpdateType::MAJOR; # the default because any change can go into major
	if (isset($_GET['release-type'])) {
		$release_update = UpdateType::getCode($_GET['release-type'], UpdateType::USAGE_STDLIB_RELEASES);
	}

	$latest_release = StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, StdlibRelease::PUBLISHED_YES);
	if ($latest_release === NULL) {
		$latest_release = '0.0.0';
	}
	$version = UpdateType::bumpVersion($latest_release, $release_update);

	if (isset($_GET['release'])) {
		$version = $_GET['release'];
	}

	$data = StdlibPending::GetEntries($version);

	foreach ($data AS $i => &$entry) {
		if (isset($action) && $entry['update'] != $action) {
			unset($data[$i]);
		}
		if (isset($_GET['name']) && $_GET['name'] != $entry['name']) {
			unset($data[$i]);
		}
		$entry['update'] = UpdateType::getName($entry['update'], UpdateType::USAGE_STDLIB);
	}
	sort($data); # make array continous

	if ($content_type == 'application/json') {
		$content = json_encode($data);
	} else if ($content_type == 'text/xml' || $content_type == 'application/xml') {
		$content = '<?xml version="1.0" encoding="utf-8" ?><ald:pending xmlns:ald="ald://api/stdlib/pending/list/schema/2012">';
		foreach ($data AS $entry) {
			$content .= '<ald:pending-entry ald:id="' . htmlspecialchars($entry['id'], ENT_QUOTES) . '" ald:comment="' . htmlspecialchars($entry['comment'], ENT_QUOTES) . '"/>';
		}
		$content .= '</ald:pending>';
	}

	http_response_code(200);
	header('Content-Type: ' . $content_type);
	echo $content;
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>