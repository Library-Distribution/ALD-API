<?php
require_once(dirname(__FILE__) . '/OutputConverter.php');

class YamlConverter implements OutputConverter {
	public function canRun() {
		return function_exists('yaml_emit');
	}

	public function convert(array $data, $namespace = NULL) {
		return yaml_emit($data);
	}
}
?>