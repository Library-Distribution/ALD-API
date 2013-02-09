<?php
	require_once("../User.php");
	require_once("../Assert.php");
	require_once("../util.php");
	require_once("../db.php");
	require_once("../config/rating.php"); # import config settings

	try
	{
		Assert::RequestMethod("POST");
		Assert::GetParameters("id", array("name", "version"));
		Assert::PostParameters("rating");

		if (!ENABLE_RATING) {
			throw new HttpException(403, NULL, 'Item rating has been disabled!');
		}
		user_basic_auth("Only registered users can rate items");

		$db_connection = db_ensure_connection();

		if (!isset($_GET["id"]))
		{
			$version = mysql_real_escape_string($_GET["version"], $db_connection);

			$db_version_cond = " AND version = '$version'";
			if (in_array($version, array("latest", "first")))
			{
				$special_version = $version;
				$db_version_cond = ""; # read all and filter below
			}

			$db_query = "SELECT HEX(id), version FROM " . DB_TABLE_ITEMS . " WHERE name = '" . mysql_real_escape_string($_GET["name"], $db_connection) . "' $db_version_cond AND reviewed != '-1'";
			$db_result = mysql_query($db_query, $db_connection);

			if (!$db_result)
			{
				throw new HttpException(500);
			}
			if (mysql_num_rows($db_result) < 1)
			{
				throw new HttpException(404);
			}

			if (isset($special_version)) # filter now if special version was used
			{
				$items = array(); # fetch all items in an array
				while ($row = mysql_fetch_assoc($db_result))
				{
					$items[] = $row;
				}

				usort($items, "semver_sort"); # sort by "version" field, following semver rules
				$db_entry = $items[$special_version == "latest" ? count($items) - 1 : 0];
			}
			else
			{
				$db_entry = mysql_fetch_assoc($db_result);
			}

			$id = $db_entry["HEX(id)"];
		}
		else
		{
			$id = mysql_real_escape_string($_GET["id"], $db_connection);
		}

		$rating = (int)mysql_real_escape_string($_POST["rating"], $db_connection);
		if ($rating < 0 || $rating > MAX_RATING)
		{
			throw new HttpException(400);
		}

		# check if user already voted
		$user_id = User::getID($_SERVER["PHP_AUTH_USER"]);
		$db_query = "SELECT * FROM " . DB_TABLE_RATINGS . " WHERE user = UNHEX('$user_id') AND item = UNHEX('$id')";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		if (mysql_num_rows($db_result) > 0)
		{
			if (!CAN_UPDATE_RATING) {
				throw new HttpException(409, NULL, 'The specified user already rated this item!');
			}
			$db_query = "UPDATE " . DB_TABLE_RATINGS . " Set rating = '$rating' WHERE user = UNHEX('$user_id') AND item = UNHEX('$id')"; # update
		}
		else
		{
			$db_query = "INSERT INTO " . DB_TABLE_RATINGS . " (user, item, rating) VALUES (UNHEX('$user_id'), UNHEX('$id'), '$rating')"; # insert
		}

		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
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