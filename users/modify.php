<?php
	require_once "../util.php";
	require_once "../modules/HttpException/HttpException.php";
	require_once "../db.php";
	require_once "../User.php";
	require_once "../Assert.php";
	require_once 'Suspension.php';
	require_once '../config/suspensions.php';

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_POST); # only allow POST requests
		Assert::GetParameters("id", "name");

		user_basic_auth("Restricted API");

		$db_connection = db_ensure_connection();

		if (isset($_GET["name"]))
		{
			$id = User::getID($_GET["name"]);
		}
		else
		{
			$id = $db_connection->real_escape_string($_GET["id"]);
		}

		if ($id != User::getID($_SERVER["PHP_AUTH_USER"]))
		{
			throw new HttpException(403);
		}

		if (!empty($_POST["name"]))
		{
			if (User::existsName($_POST["name"]))
			{
				throw new HttpException(409, NULL, "User name already taken");
			}

			$db_query = "UPDATE " . DB_TABLE_USERS . " Set name = '" . $db_connection->real_escape_string($_POST["name"]) . "' WHERE id = UNHEX('$id')";
			$db_connection->query($db_query, MYSQLI_STORE_RESULT, 'Failed to set user name');
			Assert::dbMinRows($db_connection, 'User with this ID was not found.');
		}
		if (!empty($_POST["mail"]))
		{
			if (User::existsMail($_POST["mail"]))
			{
				throw new HttpException(409, NULL, "Mail address already taken");
			}

			$mail = $db_connection->real_escape_string($_POST["mail"]);
			$suspension = Suspension::createForId($id, 'Suspended for validation of modified email address', NULL, false);

			$mail_text = str_replace(array('{$USER}', '{$ID}', '{$MAIL}', '{$SUSPENSION}'), array(User::getName($id), $id, $mail, $suspension), MAIL_CHANGE_TEMPLATE);
			if (!mail($mail,
				MAIL_CHANGE_SUBJECT,
				$mail_text,
				"FROM: noreply@{$_SERVER['HTTP_HOST']}\r\nContent-type: text/html; charset=iso-8859-1"))
			{
				throw new HttpException(500, NULL, "Failed to send activation mail to '$mail'!");
			}

			$db_query = "UPDATE " . DB_TABLE_USERS . " Set mail = '$mail' WHERE id = UNHEX('$id')";
			$db_connection->query($db_query, MYSQLI_STORE_RESULT, 'Failed to set user mail address');
			Assert::dbMinRows($db_connection, 'User with this ID was not found.');
		}
		if (!empty($_POST["password"]))
		{
			$pw = hash("sha256", $_POST["password"]);

			$db_query = "UPDATE " . DB_TABLE_USERS . " Set pw = '$pw' WHERE id = UNHEX('$id')";
			$db_connection->query($db_query, MYSQLI_STORE_RESULT, 'Failed to set user password');
			Assert::dbMinRows($db_connection, 'User with this ID was not found.');
		}
		header("HTTP/1.1 204 " . HttpException::getStatusMessage(204));
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