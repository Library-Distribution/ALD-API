<?php
require_once "../../modules/HttpException/HttpException.php";
require_once "../../db.php";
require_once "../../sql2array.php";
require_once "../../util.php";
require_once "../../Assert.php";
require_once "../../User.php";
require_once '../../util/Privilege.php';
require_once "StdlibRelease.php";
require_once "../Stdlib.php";
require_once "../StdlibPending.php";
require_once "../../UpdateType.php";

try
{
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	Assert::GetParameters("version");

	# validate accept header of request
	$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

	# Acquire published scope for releases
	#########################
	# By default, include all releases. However, allow resetting the scope to non-published only or published only.
	# This is important especially for special versions ("latest" or "first").
	# No authorization is required here, but below in case the release actually described is non-published.
	$publish_status = StdlibRelease::PUBLISHED_BOTH;
	if (isset($_GET['published'])) {
		if (in_array($_GET['published'], array('yes', 'true', '+1', 1))) {
			$publish_status = StdlibRelease::PUBLISHED_YES;
		} else if (in_array($_GET['published'], array('no', 'false', '-1'))) {
			$publish_status = StdlibRelease::PUBLISHED_NO;
		}
	}

	$release = StdlibRelease::describe($_GET["version"], $publish_status);
	if (!$release['published']) {
		user_basic_auth('Only members of the stdlib team can view unpublished releases');
		if (!User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::STDLIB)) {
			throw new HttpException(403, 'Only members of the stdlib team can view unpublished releases');
		}
	}

	$release['items'] = array_map(create_function('$item', 'return $item[\'id\'];'), Stdlib::GetItems($release['release']));

	# todo later: get frameworks in the release

	if (StdlibRelease::exists($release['release'], StdlibRelease::PUBLISHED_YES)) {
		$prev_release = StdlibRelease::previousRelease($release['release'], StdlibRelease::PUBLISHED_YES);
		$changeset = Stdlib::diff($prev_release, $release['release']); # Stdlib::diff() can handle NULL as old release
	} else {
		$changeset = StdlibPending::GetEntries($release['release']);
	}

	$release['changelog'] = array();
	foreach ($changeset AS $entry) {
		$release['changelog'][$entry['name']] = $entry['comment'];
	}

	if ($content_type == "application/json")
	{
		$content = json_encode($release);
	}
	else if ($content_type == "text/xml" || $content_type == "application/xml")
	{
		$content = '<?xml version="1.0" encoding="utf-8" ?><ald:release xmlns:ald="ald://api/stdlib/releases/describe/schema/2012"';
		foreach (array('release', 'date', 'published') AS $key) {
			$content .= ' ald:' . $key . '="' . htmlspecialchars(is_bool($release[$key]) ? ($release[$key] ? 'true' : 'false') : $release[$key], ENT_QUOTES) . '"';
		}
		$content .= '><ald:description>' . htmlspecialchars($release['description'], ENT_QUOTES) . '</ald:description><ald:items>';
		foreach ($release['items'] AS $item) {
			$content .= '<ald:item ald:id="' . htmlspecialchars($item, ENT_QUOTES) . '"/>';
		}
		$content .= '</ald:items><ald:changelog>';
		foreach ($release['changelog'] AS $item => $text) {
			$content .= '<ald:changelog-entry ald:item-name="' . htmlspecialchars($item, ENT_QUOTES) . '" ald:comment="' . htmlspecialchars($text, ENT_QUOTES) . '"/>';
		}
		$content .= '</ald:changelog></ald:release>';
	}

	http_response_code(200);
	header("Content-type: $content_type");
	echo $content;
	exit;
}
catch (HttpException $e)
{
	handleHttpException($e);
}
catch (Exception $e)
{
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>