<?php
	require_once(dirname(__FILE__) . '/db.php');
	require_once(dirname(__FILE__) . '/modules/HttpException/HttpException.php');
	require_once(dirname(__FILE__) . '/modules/semver/semver.php');

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

		public static function getUpdate($old, $new) {
			# separate versions
			$old_parts = array();
			$new_parts = array();
			if (!semver_parts($old, $old_parts) || !semver_parts($new, $new_parts)) {
				throw new HttpException(500);
			}

			if (semver_compare($old, $new) > -1) { # ensure correct version order
				throw new HttpException(500);
			}

			foreach (array('major' => self::MAJOR, 'minor' => self::MINOR, 'patch' => self::PATCH) AS $part => $type) {
				if ((int)$new_parts[$part] > (int)$old_parts[$part])
					return $type;
			}

			foreach (array('prerelease' => self::PRERELEASE_INCREASE, 'build' => self::BUILD_INCREASE) AS $part => $type) {
				if (!empty($new_parts[$part]) && !empty($old_parts[$part])) { # optional parts
					$new_fragments = explode('.', $new_parts[$part]);
					$old_fragments = explode('.', $old_parts[$part]);

					for ($index = 0; $index < min(count($new_fragments), count($old_fragments)); $index++) { # use the smaller amount of parts
						$new_frag = $new_fragments[$index]; $old_frag = $old_fragments[$index];
						if (ctype_digit($new_frag) && ctype_digit($old_frag)) {
							if ((int)$new_frag != (int)$old_frag)
								return $type;
							continue;
						}
						# at least one is non-numeric: compare by characters (chars > numeric is still ensured)
						else if ($new_frag < $old_frag || $new_frag > $old_frag)
							return $type;
					}

					if (count($new_fragments) != count($old_fragments))
						return $type;
				}
				else if ($type == self::PRERELEASE_INCREASE && empty($new_parts[$part]) && !empty($old_parts[$part])) # no prerelease > any prerelease
					return $type;
				else if ($type == self::BUILD_INCREASE && !empty($new_parts[$part]) && empty($old_parts[$part])) # any build > no build
					return $type;
			}

			throw new HttpException(500);
		}

		public static function bumpVersion($base, $type) {
			$parts = array();
			if (!semver_parts($base, $parts)) { # split into parts
				throw new HttpException(500);
			}

			$reset = array('minor' => 0, 'patch' => 0, 'prerelease' => NULL, 'build' => NULL);
			switch ($type) {
				case self::MAJOR: $field = 'major';
					break;
				case self::MINOR: $field = 'minor';
					unset($reset['minor']); # must not reset minor field
					break;
				case self::PATCH: $field = 'patch';
					unset($reset['minor']); # must not reset minor field
					unset($reset['patch']); # must not reset patch field
					break;
				default:
					throw new HttpException(500); # bumping other parts is not supported
					break;
			}

			$parts[$field] = ((int)$parts[$field]) + 1; # increase bumped version part
			foreach ($reset AS $field => $value) { # reset lower parts to default value
				$parts[$field] = $value;
			}

			return semver_string($parts);
		}
	}
?>