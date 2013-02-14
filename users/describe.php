<?php
	require_once("../modules/HttpException/HttpException.php");
	require_once("../db.php");
	require_once("../util.php");
	require_once("../User.php");
	require_once("../Assert.php");
	require_once("Suspension.php");

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
			$id = mysql_real_escape_string($_GET["id"], $db_connection);
		}

		$db_query = "SELECT name, mail, privileges, joined FROM " . DB_TABLE_USERS . " WHERE id = UNHEX('$id')";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		if (mysql_num_rows($db_result) == 1)
		{
			$user = mysql_fetch_assoc($db_result);
			$trusted_user = false;

			if (isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"])) {
				user_basic_auth(''); # if credentials are specified, they must be correct
				$trusted_user = $_SERVER['PHP_AUTH_USER'] == $user['name'] # user requests information about himself - OK.
							|| User::hasPrivilege($_SERVER['PHP_AUTH_USER'], User::PRIVILEGE_USER_MANAGE) # admins and moderators can see the mail address, to
							|| User::hasPrivilege($_SERVER['PHP_AUTH_USER'], User::PRIVILEGE_ADMIN);
			}

			$user["mail-md5"] = md5($user["mail"]);
			$user["id"] = $id;

			if (!$trusted_user) {
				unset($user["mail"]);
			} else {
				$user['suspended'] = Suspension::isSuspendedById($id);
			}
			unset($user["pw"]);

			if ($content_type == "application/json")
			{
				$content = json_encode($user);
			}
			else if ($content_type == "text/xml" || $content_type == "application/xml")
			{
				$content = "<ald:user xmlns:ald=\"ald://api/users/describe/schema/2012\"";
				foreach ($user AS $key => $value)
					$content .= " ald:$key=\"" . (is_bool($value) ? ($value ? "true" : "false") : $value) . "\"";
				$content .= "/>";
			}

			header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
			header("Content-type: $content_type");
			echo $content;
			exit;
		}
		throw new HttpException(404);
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