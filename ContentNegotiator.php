<?php
require_once(dirname(__FILE__) . '/config/content-negotiation.php');
require_once(dirname(__FILE__) . '/modules/HttpException/HttpException.php');

if (!function_exists('require_all')) {
	function require_all($dir) {
		foreach (glob($dir . '/*.php') AS $file) {
			require_once $file;
		}
	}
}
require_all('converters');

class ContentNegotiator {
	private static $output_handlers = array();
	private static $converter_instances = array();

	public static function init() {
		foreach (get_declared_classes() AS $class) {
			if (in_array('OutputConverter', class_implements($class))) {
				$instance = self::$converter_instances[$class] = new $class();
				foreach ($instance->getMimes() AS $mime) {
					self::$output_handlers[$mime] = $class;
				}
			}
		}
	}

	public static function SupportedMimeTypes() {
		$mimes = array();

		foreach (self::$output_handlers AS $mime => $handler) {
			$converter = self::$converter_instances[self::$output_handlers[$mime]];
			if ($converter->canRun()) {
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
			throw new HttpException(406, NULL, array('Content-Type' => implode($mimes, ',')));
		}
		return DEFAULT_MIME_TYPE;
	}

	public static function Output($mime, $data, $namespace) {
		$converter = self::$converter_instances[self::$output_handlers[$mime]];
		if (!$converter->canRun()) {
			throw new HttpException(500);
		}
		$content = $converter->convert($data, $namespace);

		header('Content-Type: ' . $mime);
		echo $content;
		exit;
	}
}
ContentNegotiator::init();
?>