<?php
require_once 'Registration.php';
require_once '../../Assert.php';
require_once '../../util.php';
require_once '../../User.php';
require_once '../../util/Privilege.php';
require_once '../../modules/HttpException/HttpException.php';
require_once '../../config/registration.php'; # import settings regarding registration

try {
	Registration::clear(); # clear expired registration sessions

	Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
	Assert::PostParameters(array('name', 'mail', 'password', 'password-alt'));

	if ( !PUBLIC_REGISTRATION )
	{
		user_basic_auth("Registration restricted to moderators");
		if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], Privilege::REGISTRATION))
		{
			throw new HttpException(403, "Registration restricted to moderators");
		}
	}

	if ($_POST["password"] != $_POST["password-alt"])
	{
		throw new HttpException(400, "2 different passwords were specified.");
	}

	# check if name or mail registered
	if (User::existsName($_POST['name']) || User::existsMail($_POST['mail']))
	{
		throw new HttpException(409, "A user with this name or mail address has already been registered.");
	}

	# check if name or mail pending for registration
	if (Registration::existsPending($_POST['name'], $_POST['mail'])) {
		throw new HttpException(409, "An attempt to register this user name or mail address has already been made. Unless it is completed, it will expire at some point. Retry later.");
	}

	# save registration attempt
	$id = Registration::create($_POST['name'], $_POST['mail'], $_POST['password']);

	# process mail template
	$mail_text = str_replace(array('{$NAME}', '{$MAIL}', '{$PASSWORD}', '{$ID}'), array($_POST['name'], $_POST['mail'], $_POST['password'], $id), REGISTRATION_MAIL_TEMPLATE);

	# send mail to user
	if (!mail($_POST['name'] . ' <' . $_POST['mail'] . '>', REGISTRATION_MAIL_SUBJECT, $mail_text, "From: " . REGISTRATION_MAIL_SENDER . "\r\nContent-type: text/html"))
	{
		throw new HttpException(500, "Activation mail to $_POST[mail] could not be sent.");
	}

	http_response_code(204);
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, $e->getMessage()));
}
?>