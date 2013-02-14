<?php
	require_once("../util.php");
	require_once("../modules/HttpException/HttpException.php");
	require_once("../db.php");
	require_once("../User.php");
	require_once("../Assert.php");
	require_once("../Item.php");

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_POST); # only allow POST requests
		Assert::GetParameters("id", array("name", "version"));

		user_basic_auth("Restricted API");

		$db_connection = db_ensure_connection();

		if (!isset($_GET["id"]))
		{
			$id = Item::getId($_GET["name"], $_GET["version"]);
		}
		else
		{
			$id = mysql_real_escape_string($_GET["id"], $db_connection);
		}

		if (!empty($_POST["user"]))
		{
			if  (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_ADMIN)) # not an admin
			{
				$owner = Item::getUserForId($id);
				$user = User::getID($_SERVER["PHP_AUTH_USER"]);

				if ($owner != $user) # neither admin nor the user who had uploaded the item - not allowed
				{
					throw new HttpException(403);
				}
			}

			$db_query = "UPDATE " . DB_TABLE_ITEMS . " Set user = UNHEX('" . User::getID($_POST["user"]) . "') WHERE id = UNHEX('$id')";
			if (!mysql_query($db_query, $db_connection))
			{
				throw new HttpException(500);
			}
			if (mysql_affected_rows() != 1)
			{
				throw new HttpException(404);
			}
		}
		if (isset($_POST["reviewed"]))
		{
			if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_REVIEW))
			{
				throw new HttpException(403);
			}
			if (!in_array((int)$_POST["reviewed"], array(-1, 0, 1)))
			{
				throw new HttpException(400);
			}

			$db_query = "UPDATE " . DB_TABLE_ITEMS . " Set reviewed = '" . mysql_real_escape_string($_POST["reviewed"]) . "' WHERE id = UNHEX('$id')";
			if (!mysql_query($db_query, $db_connection))
			{
				throw new HttpException(500);
			}
			if (mysql_affected_rows() != 1)
			{
				throw new HttpException(404);
			}
		}
		if (isset($_POST["default"]))
		{
			if (!User::hasPrivilege($_SERVER["PHP_AUTH_USER"], User::PRIVILEGE_DEFAULT_INCLUDE))
			{
				throw new HttpException(403);
			}
			if (!in_array((int)$_POST["default"], array(0, 1)))
			{
				throw new HttpException(400);
			}

			$db_query = "UPDATE " . DB_TABLE_ITEMS . " Set default_include = '" . mysql_real_escape_string($_POST["default"]) . "' WHERE id = UNHEX('$id')";
			if (!mysql_query($db_query, $db_connection))
			{
				throw new HttpException(500);
			}
			if (mysql_affected_rows() != 1)
			{
				throw new HttpException(404);
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