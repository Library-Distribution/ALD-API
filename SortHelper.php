<?php
require_once(dirname(__FILE__) . '/db.php');
require_once(dirname(__FILE__) . '/modules/HttpException/HttpException.php');

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

	public static function PrepareSemverSorting($table, $column, $db_cond = '') {
		$db_connection = db_ensure_connection();
		$table = $db_connection->real_escape_string($table);
		$column = $db_connection->real_escape_string($column);

		$db_query = 'DROP TEMPORARY TABLE IF EXISTS `semver_index`';
		$db_connection->query($db_query);

		$db_query = 'CREATE TEMPORARY TABLE `semver_index` ('
					. '`position` int NOT NULL AUTO_INCREMENT PRIMARY KEY,'
					. '`version` varchar(50) NOT NULL'
				. ') SELECT DISTINCT `' . $column . '` AS version FROM `' . $table . '` ' . $db_cond;
		$db_connection->query($db_query);

		$db_query = 'CALL semver_sort()';
		$db_connection->query($db_query);
	}
}
?>