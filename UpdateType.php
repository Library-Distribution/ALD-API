<?php
	require_once(dirname(__FILE__) . '/db.php');
	require_once(dirname(__FILE__) . '/modules/HttpException/HttpException.php');

	class UpdateType
	{
		const MAJOR = 4;
		const MINOR = 3;
		const PATCH = 2;

		const BUILD_INCREASE = 6;
		const PRERELEASE_INCREASE = 7;

		const ADD = 1;
		const REMOVE = 5;

		private static $map = array(self::MAJOR => 'major', self::MINOR => 'minor', self::PATCH => 'patch',
						self::BUILD_INCREASE => 'build-increase', self::PRERELEASE_INCREASE => 'prerelease-increase',
						self::ADD => 'add', self::REMOVE => 'remove');

		const USAGE_ITEMS = 'items';
		const USAGE_STDLIB = 'stdlib';
		const USAGE_STDLIB_RELEASES = 'stdlib_releases';

		private static $usage = array(self::USAGE_ITEMS => array(self::MAJOR, self::MINOR, self::PATCH, self::BUILD_INCREASE, self::PRERELEASE_INCREASE, self::ADD),
							self::USAGE_STDLIB => array(self::MAJOR, self::MINOR, self::PATCH, self::ADD, self::REMOVE),
							self::USAGE_STDLIB_RELEASES => array(self::MAJOR, self::MINOR, self::PATCH));

		public static function getCode($str, $usage)
		{
			$code = array_search(strtolower($str), self::$map);
			if (!$code)
			{
				throw new HttpException(400);
			}

			if (!isset(self::$usage[$usage]) || array_search($code, self::$usage[$usage]) === FALSE)
			{
				throw new HttpException(400);
			}

			return $code;
		}

		public static function getName($id)
		{
			if (isset(self::$map[$id]))
				return self::$map[$id];
			throw new HttpException(500, NULL, 'Unknown update type!');
		}
	}
?>