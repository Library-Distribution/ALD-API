<?php
require_once(dirname(__FILE__) . '/modules/HttpException/HttpException.php');

require_once(dirname(__FILE__) . '/JsonConverter.php');
require_once(dirname(__FILE__) . '/YamlConverter.php');

define('DEFAULT_MIME_TYPE', 'application/json');

class ContentNegotiator {
	private static $output_handlers = array('application/json' => 'JsonConverter', 'text/x-yaml' => 'YamlConverter');

	public static function SupportedMimeTypes() {
		$mimes = array();
		$instances = array();

		foreach (self::$output_handlers AS $mime => $handler) {
			if (!isset($instances[$handler])) {
				if (!class_exists($handler)) {
					continue;
				}
				$instances[$handler] = new $handler();
				if (!($instances[$handler] instanceof OutputConverter)) {
					throw new HttpException(500, NULL, 'Unsupported converter for mime type "' . $mime . '"!');
				}
			}

			if ($instances[$handler]->canRun()) {
				$mimes[] = $mime;
			}
		}

		return $mimes;
	}

	public static function MimeType() {
		$additional = func_get_args();
		$mimes = array_unique(array_merge(self::SupportedMimeTypes(), $additional));

		if (isset($_SERVER['HTTP_ACCEPT'])) {
			foreach(explode(',', $_SERVER['HTTP_ACCEPT']) as $value) {
				list($suggested_mime) = explode(';', $value);
				if (in_array($suggested_mime, $mimes)) {
					return $suggested_mime;
				}
			}
			throw new HttpException(406, array('Content-Type' => implode($mimes, ',')));
		}
		return DEFAULT_MIME_TYPE;
	}

	public static function Output(array $data, $namespace) {
		$mime = self::MimeType();
		$conv = new self::$output_handlers[$mime]();
		$content = $conv->convert($data, $namespace); # canRun() is checked by <SupportedMimeTypes> already

		header('Content-Type: ' . $mime);
		echo $content;
		exit;
	}
}
?>