<?php
	require_once("../../HttpException.php");
	require_once("../../db.php");
	require_once("../../util.php");

	try
	{
		$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
		if ($request_method == "GET")
		{
			# validate accept header of request
			$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

			# connect to database server
			$db_connection = db_ensure_connection();

			# TODO: any conditions here
			$db_cond = "";
			## if not auth & review team: only released (isset(date))
			## filter: only stable / unstable

			$db_query = "SELECT `release` FROM " . DB_TABLE_STDLIB_RELEASES . " $db_cond";
			$db_result = mysql_query($db_query, $db_connection);
			if (!$db_result)
			{
				throw new HttpException(500, NULL, mysql_error() . " - \"" . $db_query . "\"");
			}

			$releases = array();
			while ($release = mysql_fetch_assoc($db_result))
			{
				$releases[] = $release["release"];
			}

			if ($content_type == "application/json")
			{
				$content = json_encode($releases);
			}
			else if ($content_type == "text/xml" || $content_type == "application/xml")
			{
				$content = "<ald:releases xmlns:ald=\"ald://api/stdlib/releases/list/schema/2012\">";
				foreach ($releases AS $release)
				{
					$content .= "<ald:release ald:version=\"$release\"/>";
				}
				$content .= "</ald:releases>";
			}

			header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
			header("Content-type: $content_type");
			echo $content;
			exit;
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