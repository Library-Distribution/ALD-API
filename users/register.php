<?php
	require_once("../db.php");
	require_once("../util.php");
	require_once("../HttpException.php");
	require_once("../User.php");

	require_once("../config/registration.php"); # import settings regarding registration

	clear_registrations(); # clear expired registration sessions

	try
	{
		$request_method = strtoupper($_SERVER["REQUEST_METHOD"]);
		$mode = strtolower($_GET["mode"]);
		$db_connection = db_ensure_connection();

		if ($mode == "init")
		{
			if ($request_method == "POST")
			{
				if (empty($_POST["name"]) || empty($_POST["mail"]) || empty($_POST["password"]) || empty($_POST["password-alt"]) || empty($_POST["template"]))
				{
					throw new HttpException(400);
				}

				if ($_POST["password"] != $_POST["password-alt"])
				{
					throw new HttpException(400, NULL, "2 different passwords were specified.");
				}

				$name = mysql_real_escape_string($_POST["name"], $db_connection);
				$mail = mysql_real_escape_string($_POST["mail"], $db_connection);
				$password = mysql_real_escape_string($_POST["password"], $db_connection);

				# check if name or mail pending for registration
				$db_query = "SELECT * FROM " . DB_TABLE_REGISTRATION . " WHERE name = '$name' OR mail = '$mail'";
				$db_result = mysql_query($db_query, $db_connection);
				if (mysql_num_rows($db_result) > 0)
				{
					throw new HttpException(409, NULL, "An attempt to register this user name or mail address has already been made."
														. "Unless it is completed, it will expire at some point. Retry later.");
				}

				# check if name or mail registered
				if (User::existsName($name) || User::existsMail($mail))
				{
					throw new HttpException(409, NULL, "A user with this name or mail address has already been registered.");
				}

				# create random token
				$chars = str_split("ABCDEFGHKLMNPQRSTWXYZ23456789");
				shuffle($chars);
				$token = implode(array_slice($chars, 0, 10));

				# generate unique session ID
				$id = mt_rand();

				# process mail template
				$template = $_POST["template"];
				foreach (array("NAME" => $name, "MAIL" => $mail, "PASSWORD" => $password, "TOKEN" => $token) AS $var => $val)
				{
					$template = str_replace("{%$var%}", $val, $template);
				}

				# send mail to user
				if (!mail("$name <$mail>", "Confirm your registration", $template, "From: noreply@{$_SERVER["Name"]}\r\nContent-type: text/html"))
				{
					throw new HttpException(500, NULL, "Activation mail to $mail could not be sent.");
				}

				# save registration attempt
				$db_query = "INSERT INTO " . DB_TABLE_REGISTRATION . " (id, token, name, mail, password) VALUES ('$id', '$token', '$name', '$mail', '$password')";
				$db_result = mysql_query($db_query, $db_connection);
				if (!$db_result)
				{
					throw new HttpException(500, NULL, mysql_error());
				}

				header("HTTP/1.1 204 " . HttpException::getStatusMessage(204));
				exit;
			}
			else
			{
				throw new HttpException(405, NULL, array("Allow" => "POST"));
			}
		}
		else if ($mode == "token")
		{
			$id = mysql_real_escape_string($_GET["id"], $db_connection);

			$db_query = "SELECT token FROM " . DB_TABLE_REGISTRATION . " WHERE id = '$id'";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, mysql_error());
			}
			if (mysql_num_rows($db_result) < 1)
			{
				throw new HttpException(404);
			}

			$row = mysql_fetch_assoc($db_result);
			$token = $row["token"];

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
			if (empty($_POST["token"]))
			{
				throw new HttpException(400);
			}

			$token = mysql_real_escape_string($_POST["token"], $db_connection);
			$id = mysql_real_escape_string($_GET["id"], $db_connection);

			$db_query = "SELECT * FROM " . DB_TABLE_REGISTRATION . " WHERE id = '$id'";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, mysql_error());
			}
			if (mysql_num_rows($db_result) < 1)
			{
				throw new HttpException(404);
			}

			$row = mysql_fetch_assoc($db_result);
			if ($row["token"] == $token)
			{
				# create account
				$pw = hash("sha256", $row["password"]);
				$db_query = "INSERT INTO " . DB_TABLE_USERS . " (id, name, mail, pw) VALUES (UNHEX(REPLACE(UUID(), '-', '')), '{$row["name"]}', '{$row["mail"]}', '$pw')";
				$db_result = mysql_query($db_query, $db_connection);
				if (!$db_result)
				{
					throw new HttpException(500, NULL, mysql_error());
				}

				# delete registration session
				$db_query = "DELETE FROM " . DB_TABLE_REGISTRATION . " WHERE id = '$id'";
				$db_result = mysql_query($db_query, $db_connection);
				if (!$db_result)
				{
					throw new HttpException(500, NULL, mysql_error());
				}

				# get user ID
				$db_query = "SELECT HEX(id) FROM " . DB_TABLE_USERS . " WHERE name = '{$row["name"]}'";
				$db_result = mysql_query($db_query, $db_connection);
				if (!$db_result)
				{
					throw new HttpException(500, NULL, mysql_error());
				}
				$temp = mysql_fetch_assoc($db_result);

				######################### POST to config-defined URLs #########################
				$urls = explode(' ', POST_REGISTRATION_URLS);

				# set CURL options
				$conn = curl_init();
				curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($conn, CURLOPT_POST, true);
				curl_setopt($conn, CURLOPT_POSTFIELDS, array("user" => $row["name"], "id" => $temp["HEX(id)"], "mail" => $row["mail"]));

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
?>
<?php
	function clear_registrations()
	{
		$db_connection = db_ensure_connection();

		$db_query = "DELETE FROM " . DB_TABLE_REGISTRATION . " WHERE DATE_ADD(created, INTERVAL " . REGISTRATION_TIMEOUT . ") <= NOW()";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500, NULL, mysql_error());
		}
	}
?>