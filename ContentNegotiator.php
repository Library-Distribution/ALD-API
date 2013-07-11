<?php
require_once(dirname(__FILE__) . '/config/content-negotiation.php');
require_once(dirname(__FILE__) . '/modules/HttpException/HttpException.php');

require_once(dirname(__FILE__) . '/JsonConverter.php');
require_once(dirname(__FILE__) . '/YamlConverter.php');

class ContentNegotiator {
	private static $output_handlers = array('application/json' => 'JsonConverter', 'text/x-yaml' => 'YamlConverter');
	private static $converter_instances = array();

	public static function SupportedMimeTypes() {
		$mimes = array();

		foreach (self::$output_handlers AS $mime => $handler) {
			$converter = self::get_converter($mime);
			if ($converter->canRun()) {
				$mimes[] = $mime;
			}
		}

		return $mimes;
	}

	private static get_converter($mime) {
		$class = self::$output_handlers[$mime];

		if (!isset(self::$converter_instances[$class])) {
			if (!class_exists($class)) {
				continue;
			}
			self::$converter_instances[$class] = new $class();
			if (!(self::$converter_instances[$class] instanceof OutputConverter)) {
				throw new HttpException(500, 'Unsupported converter for mime type "' . $mime . '"!');
			}
		}

		return self::$converter_instances[$class];
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
			throw new HttpException(406, NULL, array('Content-Type' => implode($mimes, ',')));
		}
		return DEFAULT_MIME_TYPE;
	}

	public static function Output($mime, $data, $namespace) {
		$converter = self::get_converter($mime);
		if (!$converter->canRun()) {
			throw new HttpException(500);
		}
		$content = $converter->convert($data, $namespace);

		header('Content-Type: ' . $mime);
		echo $content;
		exit;
	}
}
?>