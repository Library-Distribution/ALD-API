<?php
function sql2array($db_result, $item_handler = NULL) {
	$arr = array();
	$i = 0;
	while ($db_row = mysql_fetch_assoc($db_result)) {
		$key = $i;
		$arr[$key] = $item_handler !== NULL ? (is_array($item_handler) ? call_user_func($item_handler, $db_row, $key) : $item_handler($db_row, $key)) : $db_row;
		$i++;
	}
	return $arr;
}
?>