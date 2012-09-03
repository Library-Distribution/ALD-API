<?php
	require_once("../HttpException.php");
	require_once("../db.php");
	require_once("../util.php");
	require_once("../Item.php");

	require_once("../config/upload.php"); # import settings for upload

	try
	{
		$request_method = strtoupper($_SERVER["REQUEST_METHOD"]);

		if ($request_method == "POST")
		{
			# authentication
			user_basic_auth("Restricted API");
			$user = $_SERVER["PHP_AUTH_USER"];

			if (!ENABLE_UPLOAD)
			{
				throw new HttpException(403, NULL, "Uploads have been disabled");
			}

			if (isset($_FILES["package"]))
			{
				# validate accept header of request
				$content_type = get_preferred_mimetype(array("application/json", "text/xml", "application/xml"), "application/json");

				$pack_file = $_FILES["package"];

				# connect to database server
				$db_connection = db_ensure_connection();

				# upload and read file:
				###########################################################
				if ($pack_file["size"] > MAX_UPLOAD_SIZE)
				{
					throw new HttpException(413, NULL, "File must not be > " . MAX_UPLOAD_SIZE . " bytes.");
				}

				ensure_upload_dir(); # ensure the directory for uploads exists
				$file = find_free_file(UPLOAD_FOLDER, ".zip");
				move_uploaded_file($pack_file["tmp_name"], $file);

				$data = read_package($file, array("id", "name", "version", "type", "description", "tags")); # todo: read and parse file
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
				$escaped_name = mysql_real_escape_string($pack_name, $db_connection);
				$escaped_type = mysql_real_escape_string($pack_type, $db_connection);
				$escaped_version = mysql_real_escape_string($pack_version, $db_connection);
				$escaped_description = mysql_real_escape_string($pack_description, $db_connection);
				$escaped_tags = mysql_real_escape_string($pack_tags, $db_connection);

				# check if there's any version of the app
				if (Item::exists($pack_name))
				{
					$owner = User::getName(Item::getUser($pack_name, "latest"));
					if ($owner != $user)
					{
						throw new HttpException(403, NULL, "The user '$user' is not allowed to update the library or app '$pack_name'");
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

				# add the database entry
				$db_query = "INSERT INTO " . DB_TABLE_ITEMS . " (id, name, type, version, file, user, description, tags)
							VALUES (UNHEX('$pack_id'), '$escaped_name', '$escaped_type', '$escaped_version', '".basename($file)."', UNHEX('" . User::getID($user) . "'), '$escaped_description', '$escaped_tags')";
				$db_result = mysql_query($db_query, $db_connection);
				if (!$db_result)
				{
					throw new HttpException(500);
				}

				if ($content_type == "application/json")
				{
					$content = "{ \"id\" : \"$pack_id\" }";
				}
				else if ($content_type == "text/xml" || $content_type == "application/xml")
				{
					$content = "<ald:item-id xmlns:ald='ald://api/items/add/schema/2012' ald:id='$pack_id'/>";
				}

				header("HTTP/1.1 200 " . HttpException::getStatusMessage(200));
				header("Content-type: $content_type");
				echo $content;
				exit;
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
