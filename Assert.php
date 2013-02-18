<?php
	require_once(dirname(__FILE__) . "/modules/HttpException/HttpException.php");
	class Assert
	{
		public static function RequestMethod()
		{
			$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
			$methods = func_get_args();
			$methods = array_map('strtoupper', $methods);

			if (!in_array($request_method, $methods)) {
				throw new HttpException(405, array("Allow" => implode(", ", $methods)));
			}
		}

		const REQUEST_METHOD_GET = 'GET';
		const REQUEST_METHOD_POST = 'POST';
		const REQUEST_METHOD_DELETE = 'DELETE';

		public static function GetParameters()
		{
			return self::parameters(func_get_args(), $_GET);
		}

		public static function PostParameters()
		{
			return self::parameters(func_get_args(), $_POST);
		}

		private static function parameters($sets, $arr)
		{
			$satisfied = true; # init here in case the foreach has 0 iterations
			foreach ($sets AS $set)
			{
				$satisfied = true; # reset on each iteration

				if (!is_array($set))
					$set = array($set);

				foreach ($set as $param)
				{
					if (empty($arr[$param]))
					{
						$satisfied = false;
						break;
					}
				}
				if ($satisfied)
					break;
			}
			if (!$satisfied)
				throw new HttpException(400);
		}

		public static function HTTPS() {
			if (empty($_SERVER['HTTPS']) && $_SERVER['SERVER_ADDR'] != '127.0.0.1') {
				throw new HttpException(403, NULL, 'Must use HTTPS for authenticated API!');
			}
		}

		public static function credentials($realm = '') {
			if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
				throw new HttpException(401, array('WWW-Authenticate' => 'Basic realm="' . $realm . '"'));
			}
		}
	}
?>