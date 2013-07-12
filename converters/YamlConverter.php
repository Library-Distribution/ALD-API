<?php
require_once(dirname(__FILE__) . '/OutputConverter.php');

class YamlConverter implements OutputConverter {
	public function canRun() {
		return function_exists('yaml_emit');
	}

	public function getMimes() {
		return array('text/x-yaml');
	}

	public function convert($data, $namespace = NULL) {
		return yaml_emit($data);
	}
}
?>