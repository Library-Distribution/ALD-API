<?php
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
}
?>