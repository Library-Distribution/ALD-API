<?php
require_once(dirname(__FILE__) . '/OutputConverter.php');

class JsonConverter implements OutputConverter {
	public function canRun() {
		return function_exists('json_encode');
	}

	public function convert(array $data, $nodes = NULL) {
		return json_encode($data);
	}
}
?>