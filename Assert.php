<?php
	require_once("HttpException.php");
	class Assert
	{
		public static function RequestMethod($method)
		{
			$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
			if (!is_array($method))
			{
				if ($request_method != strtoupper($method))
					throw new HttpException(405, array("Allow" => $method));
			}
			else
			{
				if (!in_array($request_method, array_map("strtoupper", $method)))
					throw new HttpException(405, array("Allow" => implode(", ", $method)));
			}
		}

		public static function GetParameters($sets)
		{
			return self::parameters($sets, $_GET);
		}

		public static function PostParameters($sets)
		{
			return self::parameters($sets, $_POST);
		}

		private static function parameters($sets, $arr)
		{
			if (!is_array($sets))
			{
				$sets = array($sets);
			}

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