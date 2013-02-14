<?php
	require_once("../util.php");
	require_once("../modules/HttpException/HttpException.php");
	require_once("../db.php");
	require_once("../User.php");
	require_once("../Assert.php");

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
			$id = mysql_real_escape_string($_GET["id"], $db_connection);
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

			$db_query = "UPDATE " . DB_TABLE_USERS . " Set name = '" . mysql_real_escape_string($_POST["name"], $db_connection) . "' WHERE id = UNHEX('$id')";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, "Failed to set user name.");
			}
			if (mysql_affected_rows($db_connection) != 1)
			{
				throw new HttpException(404, NULL, "User with this ID was not found.");
			}
		}
		if (!empty($_POST["mail"]))
		{
			if (User::existsMail($_POST["mail"]))
			{
				throw new HttpException(409, NULL, "Mail address already taken");
			}

			$mail = mysql_real_escape_string($_POST["mail"], $db_connection);
			$token = mt_rand();

			$db_query = "UPDATE " . DB_TABLE_USERS . " Set mail = '$mail', activationToken = '$token' WHERE id = UNHEX('$id')";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, "Failed to set user mail address.");
			}
			if (mysql_affected_rows($db_connection) != 1)
			{
				throw new HttpException(404, NULL, "User with this ID was not found.");
			}

			$url = "http://" . $_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'] . "?name=$name&mode=activate&token=$token";
			if (!mail($mail,
				"Confirm ALD email address change",
				"To reactivate your account, go to <a href='$url'>$url</a>.",
				"FROM: noreply@{$_SERVER['HTTP_HOST']}\r\nContent-type: text/html; charset=iso-8859-1"))
			{
				throw new HttpException(500, NULL, "Failed to send activation mail to '$mail'!");
			}
		}
		if (!empty($_POST["password"]))
		{
			$pw = hash("sha256", $_POST["password"]);

			$db_query = "UPDATE " . DB_TABLE_USERS . " Set pw = '$pw' WHERE id = UNHEX('$id')";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, "Failed to set user password.");
			}
			if (mysql_affected_rows($db_connection) != 1)
			{
				throw new HttpException(404, NULL, "User with this ID was not found.");
			}
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