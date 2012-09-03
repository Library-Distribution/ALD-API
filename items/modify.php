<?php
	require_once("../util.php");
	require_once("../HttpException.php");
	require_once("../db.php");
	require_once("../User.php");
	require_once("../Item.php");

	try
	{
		user_basic_auth("Restricted API");

		$request_method = strtoupper($_SERVER["REQUEST_METHOD"]);
		if ($request_method == "POST")
		{
			if (isset($_GET["id"]) || (isset($_GET["name"]) && isset($_GET["version"])))
			{
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
					throw new HttpException(423); # block

					/*
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
					*/
				}
				header("HTTP/1.1 204 " . HttpException::getStatusMessage(204));
			}
			else
			{
				throw new HttpException(400);
			}
		}
		else
		{
			throw new HttpException(405, array("Allow" => "POST"));
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