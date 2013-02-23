<?php
	require_once('../../Assert.php');
	require_once('../../db.php');
	require_once('../../modules/HttpException/HttpException.php');
	require_once('../../Item.php');
	require_once('../../modules/semver/semver.php');
	require_once('../Stdlib.php');
	require_once('../StdlibPending.php');
	require_once('StdlibRelease.php');
	require_once('../../User.php');
	require_once('../../util.php');
	require_once('get_update.php');

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
		Assert::GetParameters('version');

		# todo: user validation

		$db_connection = db_ensure_connection();
		$release = mysql_real_escape_string($_GET['version'], $db_connection);

		# check if not yet released
		if (StdlibRelease::exists($release, StdlibRelease::PUBLISHED_YES))
			throw new HttpException(403, NULL, 'Cannot update a published release!');

		# get latest published release
		$latest_release = StdlibRelease::getVersion(StdlibRelease::SPECIAL_VERSION_LATEST, StdlibRelease::PUBLISHED_YES);

		# get release update type
		$release_update = get_update($latest_release, $release);

		$old_items = Stdlib::GetItems($latest_release);
		foreach ($old_items AS &$item)
		{
			$item = array_merge($item, Item::get($item['id'], array('name', 'version'))); # get name + version
		}

		# get all pending changes (stdlib_pending)
		#	* several versions of a lib / framework might occur in them
		$libs = array_map(create_function('$id', 'return array(\'id\' => $id);'), StdlibPending::GetAllEntries($release));

		$lib_version = array();
		foreach ($libs AS $i => &$lib)
		{
			$lib = array_merge($lib, Item::get($lib['id'], array('name', 'version'))); # get info on lib, especially name & version

			# assign the corresponding update types, comparing to the $old_items array
			#################################################
			$old = searchSubArray($old_items, array('name' => $lib['name']));

			if ($old && semver_compare($old['version'], $lib['version']) != 0)
			{
				if (semver_compare($old['version'], $lib['version']) == 0) # same version means removal
				{
					$update_type = UpdateType::REMOVE;
				}
				else if (semver_compare($old['version'], $lib['version']) == 1) # if any of them means a downgrade (old > new), delete the entry
				{
					StdlibPending::DeleteEntry($lib['id']);
					unset($libs[$i]);
					break;
				}
				else # actually an upgrade
				{
					$update_type = get_update($old['version'], $lib['version']); # update type
				}
			}
			else # not in latest release - must be new
			{
				$update_type = UpdateType::ADD;
			}
			#################################################

			# filter according to release update type
			#################################################
			if ($release_update == $update_type || (($update_type == UpdateType::ADD || $update_type == UpdateType::REMOVE) && $release_update == UpdateType::MAJOR))
			{
				# if duplicates: take higher, delete lower
				if (!isset($lib_version[$lib['name']]) || semver_compare($lib_version[$lib['name']], $lib['version']) < 0)
				{
					$lib_version[$lib['name']] = $lib['version'];
				}
			}
			else
			{
				unset($libs[$i]);
			}
			#################################################
		}

		# write to 'stdlib' table (deleting old entries for that release first)

		# stdlib_actions > stdlib_pending > stdlib > release published
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