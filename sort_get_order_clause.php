<?php
function sort_get_order_clause($param, $allowed) {
	$db_order = '';

	$sort_keys = explode(' ', $param);
	$sort_dirs = array_map(function($item) { return substr($item, 0, 1) != '!'; }, $sort_keys);
	$sort_keys = array_map(function($item) { return substr($item, 0, 1) == '!' ? substr($item, 1, strlen($item) - 1 ) : $item; }, $sort_keys);
	$sorting = array_combine($sort_keys, $sort_dirs);

	foreach ($sorting AS $key => $dir) {
		if (array_key_exists($key, $allowed)) {
			$db_order .= ($db_order) ? ', ' : 'ORDER BY ';
			$db_order .= $allowed[$key] . ' ' . ($dir ? 'ASC' : 'DESC');
		}
	}

	return $db_order;
}
?>