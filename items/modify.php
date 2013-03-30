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
			$id = $db_connection->real_escape_string($_GET["id"]);
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
			$db_connection->query($db_query);
			Assert::dbMinRows($db_connection);
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

			$db_query = "UPDATE " . DB_TABLE_ITEMS . " Set reviewed = '" . $db_connection->real_escape_string($_POST["reviewed"]) . "' WHERE id = UNHEX('$id')";
			$db_connection->query($db_query);
			Assert::dbMinRows($db_connection);
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