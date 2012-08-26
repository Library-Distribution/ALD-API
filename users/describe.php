<?php
	require_once("../HttpException.php");
	require_once("../db.php");
	require_once("../util.php");
	require_once("../User.php");

	try
	{
		$request_method = strtoupper($_SERVER['REQUEST_METHOD']);

		if ($request_method == "GET")
		{
			# validate accept header of request
			$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

			# connect to database server
			$db_connection = db_ensure_connection();

			if (!empty($_GET["name"]))
			{
				$id = User::getID($_GET["name"]);
			}
			else if (!empty($_GET["id"]))
			{
				$id = mysql_real_escape_string($_GET["id"], $db_connection);
			}
			else
			{
				throw new HttpException(400);
			}

			$db_query = "SELECT name, mail, pw, privileges, joined, activationToken FROM " . DB_TABLE_USERS . " WHERE id = UNHEX('$id')";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500);
			}

			if (mysql_num_rows($db_result) == 1)
			{
				$user = mysql_fetch_assoc($db_result);
				$encrypt_mail = true;

				if (isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"]))
				{
					if (!isset($_SERVER["HTTPS"]) || !$_SERVER["HTTPS"])
					{
						throw new HttpException(403, NULL, "Must use HTTPS for authenticated APIs");
					}
					$password_hash = hash("sha256", $_SERVER["PHP_AUTH_PW"]);

					if ($_SERVER["PHP_AUTH_USER"] == $user["name"] && $password_hash == $user["pw"]) # user requests information about himself - OK.
					{
						$encrypt_mail = false;
					}
					else
					{
						User::validateLogin($_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"]); # check if correct credentials specified
						$encrypt_mail =  !User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_USER_MANAGE) && !User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_ADMIN); # admin and user moderators may request this, too
					}
				}
				if ($encrypt_mail)
				{
					$user["mail"] = md5($user["mail"]);
				}
				unset($user["pw"]);
				$user["id"] = $id;
				$user["enabled"] = !$user["activationToken"]; unset($user["activationToken"]);

				if ($content_type == "application/json")
				{
					$content = json_encode($user);
				}
				else if ($content_type == "text/xml" || $content_type == "application/xml")
				{
					$content = "<ald:user xmlns:ald=\"ald://api/users/describe/schema/2012\" ald:name=\"{$user["name"]}\" ald:mail=\"{$user["mail"]}\" ald:joined=\"{$user["joined"]}\" ald:privileges=\"{$user["privileges"]}\" ald:id=\"{$user["id"]}\" ald:enabled=\"" . ($user["enabled"] ? "true" : "false") . "\"/>";
				}

				header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
				header("Content-type: $content_type");
				echo $content;
				exit;
			}
			throw new HttpException(404);
		}
		else
		{
			throw new HttpException(405, array("Allow" => "GET"));
		}
	}
	catch (HttpException $e)
	{
		handleHttpException($e);
	}
?>