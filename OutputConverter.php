<?php
interface OutputConverter {
	public function canRun();
	public function convert($data, $namespace);
}
?>