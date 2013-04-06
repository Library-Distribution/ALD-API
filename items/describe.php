<?php
	require_once("../modules/HttpException/HttpException.php");
	require_once("../db.php");
	require_once("../util.php");
	require_once('../ContentNegotiator.php');
	require_once("../Assert.php");
	require_once("../modules/semver/semver.php");
	require_once('../Item.php');

	require_once("../config/upload.php"); # import upload settings, including upload folder!

	try
	{
		# ensure correct method is used and required parameters are passed
		Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
		Assert::GetParameters("id", array("name", "version"));

		# validate accept header of request
		$content_type = ContentNegotiator::MimeType('application/x-ald-package');

		# connect to database server
		$db_connection = db_ensure_connection();

		if (!isset($_GET["id"]))
		{
			$id = Item::getId($_GET['name'], $_GET['version']);
		}
		else
		{
			$id = $db_connection->real_escape_string($_GET["id"]);
		}

		$file = UPLOAD_FOLDER . $id . '.zip';
		if ($content_type == "application/x-ald-package")
		{
			if (!Item::existsId($id)) {
				throw new HttpException(404);
			}

			$db_query = "UPDATE " . DB_TABLE_ITEMS . " Set downloads = downloads + 1 WHERE id = UNHEX('$id')";
			$db_connection->query($db_query);

			header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
			header("Content-Type: $content_type");
			header("Content-Length: " . filesize($file));
			header("Content-Disposition: attachment; filename=$id.alp");
			header("Content-MD5: " . base64_encode(md5_file($file)));
			@readfile($file);
			exit;
		}

		$db_query = "SELECT `" . DB_TABLE_ITEMS . "`.*, HEX(`" . DB_TABLE_ITEMS . "`.`user`) AS userID, `" . DB_TABLE_USERS . "`.`name` AS userName, ROUND(AVG(`rating`), 1) AS rating" # field list
					. " FROM " . DB_TABLE_ITEMS . ", " . DB_TABLE_USERS . ', ' . DB_TABLE_RATINGS															# tables to read from
					. " WHERE `" . DB_TABLE_ITEMS . "`.`user` = `" . DB_TABLE_USERS . "`.`id` AND `" . DB_TABLE_RATINGS . "`.`item` = `" . DB_TABLE_ITEMS . "`.`id`"		# table combination
					. " AND `" . DB_TABLE_ITEMS . "`.`id` = UNHEX('$id') AND `reviewed` != '-1'";																# extra criteria

		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_result);
		$db_entry = $db_result->fetch_assoc();

		$data = read_package($file);

		$output = $data;
		$output["uploaded"] = $db_entry["uploaded"];
		$output["rating"] = (float)$db_entry["rating"] ;
		$output["downloads"] = (int)$db_entry["downloads"];
		$output['user'] = array('name' => $db_entry['userName'], 'id' => $db_entry['userID']);
		$output["reviewed"] = $db_entry["reviewed"] == 1;
		$tag_list  = array();
		foreach ($data["tags"] AS $tag)
		{
			$tag_list[] = $tag["name"];
		}
		$output["tags"] = $tag_list;

		if ($content_type == "application/json")
		{
			ksort($output);
			$content = json_encode($output);
		}
		else if ($content_type == "text/xml" || $content_type == "application/xml")
		{
			$content = "<?xml version='1.0' encoding='utf-8' ?><ald:item xmlns:ald=\"ald://api/items/describe/schema/2012\"";
			# ...
			foreach ($output AS $key => $value)
			{
				if (!is_array($value))
				{
					$content .= " ald:$key=\"" . htmlspecialchars(is_bool($value) ? ($value ? "true" : "false") : $value, ENT_QUOTES) . "\"";
				}
			}
			$content .= ' ald:userID="' . htmlspecialchars($output["user"]["id"], ENT_QUOTES) . '" ald:user="' . $output["user"]["name"] . '"';
			$content .= ">";
			if (isset($output["authors"]) && is_array($output["authors"]))
			{
				$content .= "<ald:authors>";
				foreach ($output["authors"] AS $author)
				{
					$content .= '<ald:author ald:name="' . htmlspecialchars($author["name"], ENT_QUOTES) . '"';
					foreach (array('user-name', 'homepage', 'mail') AS $key){
						if (isset($author[$key])) {
							$content .= ' ald:' . $key . '="' . htmlspecialchars($author[$key], ENT_QUOTES) . '"';
						}
					}
					$content .= '/>';
				}
				$content .= "</ald:authors>";
			}
			if (isset($output["dependencies"]) && is_array($output["dependencies"]))
			{
				$content .= "<ald:dependencies>";
				foreach ($output["dependencies"] AS $dependency)
				{
					$content .= '<ald:dependency ald:name="' . htmlspecialchars($dependency["name"], ENT_QUOTES) . '">' . xml_version_switch($dependency) . "</ald:dependency>";
				}
				$content .= "</ald:dependencies>";
			}
			if (isset($output["requirements"]) && is_array($output["requirements"]))
			{
				$content .= "<ald:requirements>";
				foreach ($output["requirements"] AS $requirement)
				{
					$content .= '<ald:requirement ald:type="' . htmlspecialchars($requirement["type"], ENT_QUOTES) . '">' . xml_version_switch($requirement) . "</ald:requirement>";
				}
				$content .= "</ald:requirements>";
			}
			if (isset($output["tags"]) && is_array($output["tags"]))
			{
				$content .= "<ald:tags>";
				foreach ($output["tags"] AS $tag)
				{
					$content .= '<ald:tag ald:name="' . htmlspecialchars($tag, ENT_QUOTES) . '"/>';
				}
				$content .= "</ald:tags>";
			}
			if (isset($output["links"]) && is_array($output["links"]))
			{
				$content .= "<ald:links>";
				foreach ($output["links"] AS $link)
				{
					$content .= '<ald:link ald:name="' . htmlspecialchars($link["name"], ENT_QUOTES) . '" ald:description="' . htmlspecialchars($link["description"], ENT_QUOTES) . '" ald:href="' . htmlspecialchars($link["href"], ENT_QUOTES) . '"/>';
				}
				$content .= "</ald:links>";
			}
			$content .= "</ald:item>";
		}

		header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
		header("Content-type: $content_type");
		echo $content;
		exit;
	}
	catch (HttpException $e)
	{
		handleHttpException($e);
	}
	catch (Exception $e)
	{
		handleHttpException(new HttpException(500, NULL, $e->getMessage()));
	}

	function xml_version_switch($data)
	{
		$content = "";
		if (isset($data["version"]))
		{
			$content .= '<ald:version ald:value="' . htmlspecialchars($data["version"], ENT_QUOTES) . '"/>';
		}
		else if (isset($data["version-range"]))
		{
			$content .= '<ald:version-range ald:min-value="' . htmlspecialchars($data["version-range"]["min"], ENT_QUOTES) . '" ald:max-value="' . htmlspecialchars($data["version-range"]["max"], ENT_QUOTES) . '"/>';
		}
		else if (isset($data["version-list"]) && is_array($data["version-list"]))
		{
			$content .= "<ald:version-list>";
			foreach ($data["version-list"] AS $version)
			{
				$content .= '<ald:version ald:value="' . htmlspecialchars($version, ENT_QUOTES) . '"/>';
			}
			$content .= "</ald:version-list>";
		}
		return $content;
	}
?>
