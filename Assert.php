<?php
	require_once("modules/HttpException/HttpException.php");
	class Assert
	{
		public static function RequestMethod()
		{
			$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
			$methods = array_map('strtoupper', func_get_args());

			if (!in_array($request_method, $methods)) {
				throw new HttpException(405, array("Allow" => implode(", ", $methods)));
			}
		}

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
	}
?>