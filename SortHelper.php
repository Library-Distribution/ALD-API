<?php
require_once dirname(__FILE__) . '/db.php';

class SortHelper {
	public static function getOrderClause($list, $allowed) {
		$db_order = '';

		foreach ($list AS $key => $dir) {
			if (array_key_exists($key, $allowed)) {
				$db_order .= ($db_order) ? ', ' : 'ORDER BY ';
				$db_order .= $allowed[$key] . ' ' . ($dir ? 'ASC' : 'DESC');
			}
			# TODO: throw an error here?
		}

		return $db_order;
	}

	public static function getListFromParam($param) {
		$parts = explode(' ', $param);

		$keys = array_map(array('SortHelper', '_cleanupSortKey'), $parts);
		$dirs = array_map(array('SortHelper', '_getSortDir'), $parts);

		return array_combine($keys, $dirs);
	}

	static function _cleanupSortKey($k) {
		return ltrim($k, '!');
	}

	static function _getSortDir($k) {
		return substr($k, 0, 1) != '!';
	}

	public static function PrepareSemverSorting($table, $column, $db_cond = '', $more_semver = NULL) {
		$db_connection = db_ensure_connection();
		$table = $db_connection->real_escape_string($table);
		$column = $db_connection->real_escape_string($column);

		$db_query = 'DROP TEMPORARY TABLE IF EXISTS `semver_index`';
		$db_connection->query($db_query);

		$db_query = 'CREATE TEMPORARY TABLE `semver_index` ('
					. '`position` int NOT NULL AUTO_INCREMENT PRIMARY KEY,'
					. '`version` varchar(50) NOT NULL UNIQUE'
				. ') ENGINE=MEMORY SELECT DISTINCT `' . $column . '` AS version FROM `' . $table . '` ' . $db_cond;
		$db_connection->query($db_query);

		if ($more_semver !== NULL && count($more_semver) > 0) {
			$db_query = 'INSERT IGNORE INTO `semver_index` (`version`) VALUES ' . implode(', ', array_map(create_function('$version', 'return "(\'$version\')";'), $more_semver));
			$db_connection->query($db_query);
		}

		$db_query = 'CALL semver_sort()';
		$db_connection->query($db_query);
	}

	public static function RetrieveSemverIndex($version) {
		$db_connection = db_ensure_connection();
		$db_query = 'SELECT `position` FROM `semver_index` WHERE `version` = "' . $db_connection->real_escape_string($version) . '"';
		$db_result = $db_connection->query($db_query);

		$db_entry = $db_result->fetch_array();
		return $db_entry['position'];
	}
}
?>