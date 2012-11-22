<?php
require_once(__DIR__ . '/../db.php');
require_once(__DIR__ . '/../HttpException.php');

class StdlibPending
{
	public static function GetEntries()
	{
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT HEX(`lib`) FROM ' . DB_TABLE_STDLIB_PENDING;
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}

		$libs = array();
		while ($lib = mysql_fetch_assoc($db_result))
		{
			$libs[] = $lib['HEX(`lib`)'];
		}
		return $libs;
	}

	public static function AddEntry($id)
	{
	}

	public static function DeleteEntry($id)
	{
		$db_connection = db_ensure_connection();
		$id = mysql_real_escape_string($id, $db_connection);

		$db_query = 'DELETE * FROM ' . DB_TABLE_STDLIB_PENDING . " WHERE `id` = UNHEX('$id')";
		$db_result = mysql_query($db_query, $db_connection);
		if (!$db_result)
		{
			throw new HttpException(500);
		}
	}
}
?>