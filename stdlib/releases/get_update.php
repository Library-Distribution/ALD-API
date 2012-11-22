<?php
require_once(__DIR__ . '/../../semver.php');
require_once(__DIR__ . '/../../UpdateType.php');
require_once(__DIR__ . '/../../HttpException.php');

function get_update($old, $new)
{
	$old_parts = array();
	if (!semver_parts($old, $old_parts))
	{
		throw new HttpException(500);
	}

	$new_parts = array();
	if (!semver_parts($new, $new_parts))
	{
		throw new HttpException(500);
	}

	if (semver_compare($old, $new) > -1)
	{
		throw new HttpException(500);
	}

	foreach (array('major' => UpdateType::MAJOR, 'minor' => UpdateType::MINOR, 'patch' => UpdateType::PATCH) AS $part => $type)
	{
		$new_value = (int)$new_parts[$part];
		$old_value = (int)$old_parts[$part];

		if ($new_value > $old_value)
			return $type;
	}

	foreach (array('prerelease' => UpdateType::PRERELEASE_INCREASE, 'build' => UpdateType::BUILD_INCREASE) AS $part => $type)
	{
		if (!empty($new_parts[$part]) && !empty($old_parts[$part])) # optional parts
		{
			$new_fragments = explode('.', $new_parts[$part]);
			$old_fragments = explode('.', $old_parts[$part]);

			for ($index = 0; $index < min(count($new_fragments), count($old_fragments)); $index++) # use the smaller amount of parts
			{
				$new_frag = $new_fragments[$index]; $old_frag = $old_fragments[$index];
				if (ctype_digit($new_frag) && ctype_digit($old_frag))
				{
					$new_frag = (int)$new_frag; $old_frag = (int)$old_frag; # convert to numbers
					if ($new_frag != $old_frag)
						return $type;
					continue;
				}
				# at least one is non-numeric: compare by characters
				else if ($new_frag < $old_frag || $new_frag > $old_frag)
					return $type;
			}

			if (count($new_fragments) != count($old_fragments))
				return $type;
		}
		else if (!empty($new_parts[$part]))
			return $type;
	}

	throw new HttpException(500);
}

?>