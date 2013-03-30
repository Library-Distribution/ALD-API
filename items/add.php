<?php
	require_once("../modules/HttpException/HttpException.php");
	require_once("../db.php");
	require_once("../util.php");
	require_once("../Item.php");
	require_once("../Assert.php");
	require_once('ItemType.php');

	require_once("../config/upload.php"); # import settings for upload

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_PUT); # only allow PUT requests
		Assert::ContentLengthSpecified();
		Assert::ContentType('application/x-ald-package');

		# authentication
		user_basic_auth("Restricted API");
		$user = $_SERVER["PHP_AUTH_USER"];

		if (!ENABLE_UPLOAD)
		{
			throw new HttpException(403, NULL, "Uploads have been disabled");
		}

		# read request data in temp file
		$input = fopen('php://input', 'r');
		$temp = tmpfile();
		while ($data = fread($input,1024))
			fwrite($temp,$data);
		$temp_stat = array_merge(fstat($temp), stream_get_meta_data($temp));
		fclose($input);

		# connect to database server
		$db_connection = db_ensure_connection();

		# upload and read file:
		###########################################################
		if ($temp_stat["size"] > MAX_UPLOAD_SIZE)
		{
			throw new HttpException(413, NULL, "File must not be > " . MAX_UPLOAD_SIZE . " bytes.");
		}

		$data = read_package($temp_stat['uri'], array("id", "name", "version", "type", "description", "tags")); # todo: read and parse file
		$pack_id = $data["id"]; $pack_name = $data["name"]; $pack_version = $data["version"]; $pack_type = $data["type"];
		$pack_description = $data["description"];

		$pack_tags = array();
		foreach ($data["tags"] AS $tag)
		{
			$pack_tags[] = $tag["name"];
		}
		$pack_tags = implode(";", $pack_tags);

		# todo: validate version string / convert to number
		###########################################################

		# escape data to prevent SQL injection
		$escaped_name = $db_connection->real_escape_string($pack_name);
		$escaped_version = $db_connection->real_escape_string($pack_version);
		$escaped_description = $db_connection->real_escape_string($pack_description);
		$escaped_tags = $db_connection->real_escape_string($pack_tags);

		# check if item type is supported and read the code
		$escaped_type = ItemType::getCode($pack_type); # unsupported types throw an exception

		# check if there's any version of the item yet
		if (Item::exists($pack_name))
		{
			$owner = User::getName(Item::getUser($pack_name, "latest"));
			if ($owner != $user)
			{
				throw new HttpException(403, NULL, "The user '$user' is not allowed to update the item '$pack_name'");
			}
		}

		# check if this specific version had already been uploaded or not
		if (Item::exists($pack_name, $pack_version))
		{
			throw new HttpException(409, NULL, "The specified version '$pack_version' of package '$pack_name' has already been uploaded!");
		}

		# check if item with this GUID had already been uploaded or not
		if (Item::existsId($pack_id))
		{
			throw new HttpException(409, NULL, "An item with the specified GUID '$pack_id' has already been uploaded!");
		}

		ensure_upload_dir(); # ensure the directory for uploads exists
		rename($temp_stat['uri'], UPLOAD_FOLDER . $pack_id . '.zip');
		fclose($temp);

		# add the database entry
		$db_query = "INSERT INTO " . DB_TABLE_ITEMS . " (id, name, type, version, user, description, tags)
					VALUES (UNHEX('$pack_id'), '$escaped_name', '$escaped_type', '$escaped_version', UNHEX('" . User::getID($user) . "'), '$escaped_description', '$escaped_tags')";
		try {
			$db_connection->query($db_query);
		} catch (HttpException $e) {
			unlink(UPLOAD_FOLDER . $pack_id . '.zip');
			throw $e;
		}

		header('HTTP/1.1 204 ' . HttpException::getStatusMessage(204));
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
?>
