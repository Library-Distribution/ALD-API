<?php
class Privilege {

	# constants
	const NONE = 0;

	const MODERATOR = 2;
	const MODERATOR_ADMIN = 4;

	const REVIEW = 8;
	const REVIEW_ADMIN = 16;

	const STDLIB = 32;
	const STDLIB_ADMIN = 64;

	const REGISTRATION = 128;
	const REGISTRATION_ADMIN = 256;

	const ADMIN = 512;

	private static $privilege_map = array('none' => self::NONE, 'admin' => self::ADMIN,
							'user-mod' => self::MODERATOR, 'user-mod-admin' => self::MODERATOR_ADMIN,
							'review' => self::REVIEW, 'review-admin' => self::REVIEW_ADMIN,
							'stdlib' => self::STDLIB, 'stdlib-admin' => self::STDLIB_ADMIN,
							'registration' => self::REGISTRATION, 'registration-admin' => self::REGISTRATION_ADMIN);

	public static function toArray($privilege) {
		$arr = array();

		foreach (self::$privilege_map AS $str => $priv) {
			if ($priv !== self::NONE) {
				if (($privilege & $priv) == $priv) {
					$arr[] = $str;
				}
			} else if ($privilege == $priv) {
				$arr[] = 'none';
			}
		}

		return $arr;
	}

	public static function fromArray($arr) {
		$privilege = self::NONE;

		foreach ($arr AS $priv) {
			if (!array_key_exists($priv, self::$privilege_map)) {
				throw new HttpException(500);
			}

			if ($priv != 'none') {
				$privilege |= self::$privilege_map[$priv];
			} else if (count($arr) > 1) {
				throw new HttpException(500);
			}
		}

		return $privilege;
	}

	public static function adminPrivilege($privilege) { #adminPrivilegeForPrivilege
		$flip = array_flip(self::$privilege_map);
		if (!array_key_exists($privilege, $flip)) { # find the name for the given privilege
			throw new HttpException(400);
		}

		if (strpos($flip[$privilege], '-admin') !== FALSE) { # given privilege is a group admin => return the overall admin
			return self::ADMIN;
		}

		$key = $flip[$privilege] . '-admin'; # append '-admin' to the privilege name
		if (!array_key_exists($key, self::$privilege_map)) {
			throw new HttpException(500);
		}

		return self::$privilege_map[$key];
	}

	public static function contains($comb, $flag) {
		return ($comb & $flag) == $flag;
	}
}
?>