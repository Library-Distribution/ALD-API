<?php
require_once "../modules/HttpException/HttpException.php";
require_once "../db.php";
require_once "../util.php";
require_once "../User.php";
require_once '../util/Privilege.php';
require_once "../Assert.php";
require_once "Suspension.php";

try
{
	# ensure correct method is used and required parameters are passed
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	Assert::GetParameters("name", "id");

	# validate accept header of request
	$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

	# connect to database server
	$db_connection = db_ensure_connection();

	if (isset($_GET["name"]))
	{
		$id = User::getID($_GET["name"]);
	}
	else
	{
		$id = $db_connection->real_escape_string($_GET["id"]);
	}

	$db_query = "SELECT name, mail, privileges, joined FROM " . DB_TABLE_USERS . " WHERE id = UNHEX('$id')";
	$db_result = $db_connection->query($db_query);
	Assert::dbMinRows($db_result);

	$user = $db_result->fetch_assoc();
	$trusted_user = false;

	if (isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"])) {
		user_basic_auth(''); # if credentials are specified, they must be correct
		$trusted_user = $_SERVER['PHP_AUTH_USER'] == $user['name'] # user requests information about himself - OK.
					|| User::hasPrivilege($_SERVER['PHP_AUTH_USER'], Privilege::MODERATOR); # moderators can see the mail address, too
	}

	$user["mail-md5"] = md5($user["mail"]);
	$user["id"] = $id;
	$user['privileges'] = Privilege::toArray($user['privileges']);

	if (!$trusted_user) {
		$user['mail'] = NULL;
		$user['suspended'] = NULL;
	} else {
		$user['suspended'] = Suspension::isSuspendedById($id);
	}

	if ($content_type == "application/json")
	{
		$content = json_encode($user);
	}
	else if ($content_type == "text/xml" || $content_type == "application/xml")
	{
		$content = "<?xml version='1.0' encoding='utf-8' ?><ald:user xmlns:ald=\"ald://api/users/describe/schema/2012\"";
		foreach ($user AS $key => $value)
			$content .= " ald:$key=\"" . htmlspecialchars(is_bool($value) ? ($value ? "true" : "false") : (is_array($value) ? implode(' ', $value) : $value), ENT_QUOTES) . "\"";
		$content .= "/>";
	}

	header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
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