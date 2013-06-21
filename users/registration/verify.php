<?php
require_once 'Registration.php';
require_once '../../Assert.php';
require_once '../../util.php';
require_once '../../User.php';
require_once '../../modules/HttpException/HttpException.php';
require_once '../../db.php';
require_once '../../config/registration.php'; # import settings regarding registration

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::GetParameters('id');
	Assert::PostParameters('token');

	$session = Registration::get($_GET['id']);
	if ($session['token'] == $_POST['token']) {
		# create account
		User::create($session['name'], $session['mail'], $session['password']);

		# delete registration session
		Registration::delete($_GET['id']);

		# get user ID
		$id = User::getID($session['name']);

		######################### POST to config-defined URLs #########################
		$urls = explode(' ', POST_REGISTRATION_URLS);

		# set CURL options
		$conn = curl_init();
		curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($conn, CURLOPT_POST, true);
		curl_setopt($conn, CURLOPT_POSTFIELDS, array("user" => $session["name"], "id" => $id, "mail" => $session["mail"]));

		foreach ($urls AS $url)
		{
			curl_setopt($conn, CURLOPT_URL, $url);
			curl_exec($conn);
		}
		curl_close($conn);
		###############################################################################

		http_response_code(204);
		exit;
	}
	else
	{
		# do not delete session, let it expire
		throw new HttpException(400, "Invalid token specified");
	}

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>