<?php
require_once('Registration.php');
require_once('../../Assert.php');
require_once('../../util.php');
require_once('../../User.php');
require_once('../../modules/HttpException/HttpException.php');
require_once('../../db.php');
require_once('../../config/registration.php'); # import settings regarding registration

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters('id');
	Assert::PostParameters('token');

	$session = Registration::get($_GET['id']);
	if ($session['token'] == $_POST['token']) {
		# create account
		$pw = hash("sha256", $session["password"]);
		$db_query = "INSERT INTO " . DB_TABLE_USERS . " (id, name, mail, pw) VALUES (UNHEX(REPLACE(UUID(), '-', '')), '{$session["name"]}', '{$session["mail"]}', '$pw')";
		$db_result = mysql_query($db_query, $db_connection);
		if ($db_result === FALSE) {
			throw new HttpException(500);
		}

		# delete registration session
		Registration::delete($_GET['id']);

		# get user ID
		$temp = User::getID($row['name']);

		######################### POST to config-defined URLs #########################
		$urls = explode(' ', POST_REGISTRATION_URLS);

		# set CURL options
		$conn = curl_init();
		curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($conn, CURLOPT_POST, true);
		curl_setopt($conn, CURLOPT_POSTFIELDS, array("user" => $session["name"], "id" => $temp["id"], "mail" => $session["mail"]));

		foreach ($urls AS $url)
		{
			curl_setopt($conn, CURLOPT_URL, $url);
			curl_exec($conn);
		}
		curl_close($conn);
		###############################################################################

		header("HTTP/1.1 204 " . HttpException::getStatusMessage(204));
		exit;
	}
	else
	{
		# do not delete session, let it expire
		throw new HttpException(400, NULL, "Invalid token specified");
	}

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, NULL, $e->getMessage()));
}
?>