<?php
	require_once('registrationRegistration.php');
	require_once("../db.php");
	require_once("../util.php");
	require_once("../modules/HttpException/HttpException.php");
	require_once("../User.php");

	require_once("../config/registration.php"); # import settings regarding registration

	Registration::clear(); # clear expired registration sessions

	try
	{
		$mode = strtolower($_GET["mode"]);
		$db_connection = db_ensure_connection();

		if ($mode == "init")
		{
			Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
			Assert::PostParameters(array('name', 'mail', 'password', 'password-alt'));

			if ( !PUBLIC_REGISTRATION )
			{
				user_basic_auth("Registration restricted to moderators");
				if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_REGISTRATION))
				{
					throw new HttpException(403, NULL, "Registration restricted to moderators");
				}
			}

			if ($_POST["password"] != $_POST["password-alt"])
			{
				throw new HttpException(400, NULL, "2 different passwords were specified.");
			}

			# check if name or mail registered
			if (User::existsName($_POST['name']) || User::existsMail($_POST['mail']))
			{
				throw new HttpException(409, NULL, "A user with this name or mail address has already been registered.");
			}

			# check if name or mail pending for registration
			if (Registration::existsPending($_POST['name'], $_POST['mail'])) {
				throw new HttpException(409, NULL, "An attempt to register this user name or mail address has already been made. Unless it is completed, it will expire at some point. Retry later.");
			}

			# save registration attempt
			$id = Registration::create($_POST['name'], $_POST['mail'], $_POST['password']);

			# process mail template
			$mail_text = str_replace(array('{$NAME}', '{$MAIL}', '{$PASSWORD}', '{$ID}'), array($_POST['name'], $_POST['mail'], $_POST['password'], $id), REGISTRATION_MAIL_TEMPLATE);

			# send mail to user
			if (!mail($_POST['name'] . ' <' . $_POST['mail'] . '>', REGISTRATION_MAIL_SUBJECT, $mail_text, "From: noreply@{$_SERVER["Name"]}\r\nContent-type: text/html"))
			{
				throw new HttpException(500, NULL, "Activation mail to $_POST[mail] could not be sent.");
			}

			header("HTTP/1.1 204 " . HttpException::getStatusMessage(204));
			exit;
		}
		else if ($mode == "token")
		{
			Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
			Assert::GetParameters('id');

			$session = Registration::get($_GET['id']);
			$token = $session['token'];

			$char_width = 12;
			$image = imagecreate(20 + $char_width * strlen($token), 50);
			imagecolorallocate($image, 0, 0, 0);
			$color = imagecolorallocate($image, 250, 200, 255);
			$i = 0;
			foreach (str_split($token) AS $char)
			{
				$y = rand(1, 20);
				imagechar($image, 5, 10 + $i * $char_width, $y, $char, $color);
				$i++;
			}
			header("Content-type: image/png");
			imagepng($image);
			exit;
		}
		else if ($mode == "verify")
		{
			Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
			Assert::GetParameters('id');
			Assert::PostParameters('token');

			$session = Registration::get($_GET['id']);
			if ($session['token'] == $_POST['token'])
			{
				# create account
				$pw = hash("sha256", $session["password"]);
				$db_query = "INSERT INTO " . DB_TABLE_USERS . " (id, name, mail, pw) VALUES (UNHEX(REPLACE(UUID(), '-', '')), '{$session["name"]}', '{$session["mail"]}', '$pw')";
				$db_result = mysql_query($db_query, $db_connection);
				if (!$db_result)
				{
					throw new HttpException(500, NULL, mysql_error());
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
		}
		else
		{
			throw new HttpException(404);
		}
	}
	catch (HttpException $e)
	{
		handleHttpException($e);
	}
	catch (Exception $e)
	{
		handleHttpException(new HttpException(500, NULL, $e->getMessage()));
	}
?>