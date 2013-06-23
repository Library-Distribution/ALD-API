<?php
require_once dirname(__FILE__) . "/db.php";
require_once dirname(__FILE__) . "/modules/HttpException/HttpException.php";
require_once dirname(__FILE__) . "/User.php";
require_once dirname(__FILE__) . "/Assert.php";

function user_basic_auth($realm)
{
	Assert::HTTPS();
	Assert::credentials($realm);
	User::validateLogin($_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"]);
}

function find_free_directory($parent = "")
{
	do
	{
		$dir = rand();
	} while(is_dir($parent . $dir));
	return $parent . $dir . DIRECTORY_SEPARATOR;
}

function ensure_upload_dir()
{
	require_once dirname(__FILE__) . "/config/upload.php";
	if (!is_dir(UPLOAD_FOLDER))
	{
		mkdir(UPLOAD_FOLDER);
	}
}

function get_preferred_mimetype($available, $default)
{
	if (isset($_SERVER["HTTP_ACCEPT"]))
	{
		foreach(explode(",", $_SERVER["HTTP_ACCEPT"]) as $value)
		{
			$acceptLine = explode(";", $value);
			if (in_array($acceptLine[0], $available))
			{
				return $acceptLine[0];
			}
		}
		throw new HttpException(406, NULL, array("Content-type" => implode($available, ",")));
	}
	return $default;
}

if (!function_exists('http_response_code')) {
	function http_response_code($code) {
		header(' ', true, $code);
	}
}

function handleHttpException($e)
{
	http_response_code($e->getCode());
	if (is_array($e->getHeaders()))
	{
		foreach ($e->getHeaders() AS $header => $value)
		{
			header($header . ": " . $value);
		}
	}
	echo "ERROR: " . $e->getCode() . " - " . $e->getMessage();
}

# SOURCE: http://www.php.net/manual/de/function.rmdir.php#108113
# recursively remove a directory
function rrmdir($dir) {
	foreach(glob($dir . "/*") as $file) {
		if(is_dir($file))
			rrmdir($file);
		else
			unlink($file);
	}
	rmdir($dir);
}

# SOURCE: http://stackoverflow.com/a/6303043
# modified to allow an array of key-value pairs instead of just one
function searchSubArray(Array $array, $key_values)
{
	foreach ($array as $index => $subarray)
	{
		$match = true;
		foreach ($key_values AS $key => $value)
		{
			$match = $match && (isset($subarray[$key]) && $subarray[$key] == $value);
		}
		if ($match)
			return $index;
	}
}
?>