<?php
interface OutputConverter {
	public function canRun();
	public function convert(array $data, $nodes = NULL);
}
?>