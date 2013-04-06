<?php
	require_once("../User.php");
	require_once("../Item.php");
	require_once("../Assert.php");
	require_once("../util.php");
	require_once("../db.php");
	require_once('../sql2array.php');
	require_once("../config/rating.php"); # import config settings

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_POST, Assert::REQUEST_METHOD_GET);
		Assert::GetParameters("id", array("name", "version"));

		if (!ENABLE_RATING) {
			throw new HttpException(403, NULL, 'Item rating has been disabled!');
		}
		$db_connection = db_ensure_connection();

		if (!isset($_GET["id"]))
		{
			$id = Item::getId($_GET['name'], $_GET['version']);
		}
		else
		{
			$id = mysql_real_escape_string($_GET["id"], $db_connection);
		}

		$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
		if ($request_method == Assert::REQUEST_METHOD_POST) {
			Assert::PostParameters("rating");
			user_basic_auth("Only registered users can rate items");

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

		} else if ($request_method == Assert::REQUEST_METHOD_GET) {
			# validate accept header of request
			$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

			$db_query = 'SELECT name AS user, rating FROM ' . DB_TABLE_RATINGS . ', ' . DB_TABLE_USERS . ' WHERE item = UNHEX("' . $id . '") AND `user` = `id`';
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result) {
				throw new HttpException(500);
			}
			$ratings = sql2array($db_result, 'clean_entry');

			if ($content_type == "application/json") {
				$content = json_encode($ratings);
			} else if ($content_type == "text/xml" || $content_type == "application/xml") {
				$content  = '<?xml version="1.0" encoding="utf-8" ?><ald:ratings xmlns:ald="ald://api/items/rating/schema/2012">';
				foreach ($ratings AS $user => $rating) {
					$content .= '<ald:rating ald:user="' . htmlspecialchars($user, ENT_QUOTES) . '" ald:value="' . htmlspecialchars($rating, ENT_QUOTES) . '"/>';
				}
				$content .= '</ald:ratings>';
			}

			header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
			header("Content-type: $content_type");
			echo $content;
			exit;
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
<?php
function clean_entry($row, &$key) {
	$key = $row['user'];
	return $row['rating'];
}
?>