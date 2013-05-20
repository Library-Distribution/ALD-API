<?php
require_once(dirname(__FILE__) . '/../../config/review.php');

require_once(dirname(__FILE__) . '/../../db.php');
require_once(dirname(__FILE__) . '/../../modules/HttpException/HttpException.php');

class Review {
	public static function Review($item, $user, $accept, $reason, $final = false) {
		$db_connection = db_ensure_connection();

		$item = $db_connection->real_escape_string($item);
		$user = $db_connection->real_escape_string($user);
		$reason = $db_connection->real_escape_string($reason);
		$accept = (bool)$accept;
		$final = (bool)$final;

		if (self::HasReviewed($item, $user)) {
			if (!REVIEW_CAN_UPDATE) {
				throw new HttpException(403, NULL, 'You cannot update your previous review.');
			}
			$db_query = 'UPDATE `' . DB_TABLE_REVIEWS . '` SET `accept` = ' . $accept . ', `final` = ' . $final . ', `reason` = "' . $reason . '", `date` = NOW() WHERE `user` = UNHEX("' . $user . '")';
		} else {
			$db_query = 'INSERT INTO `' . DB_TABLE_REVIEWS . '` (`item`, `user`, `accept`, `final`, `reason`) VALUES (UNHEX("' . $item . '", UNHEX("' . $user . '", ' . $accept . ', ' . $final . ', "' . $reason . '")';
		}

		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_connection);
	}

	public static function HasReviewed($item, $user) {
		$db_connection = db_ensure_connection();
		$item = $db_connection->real_escape_string($item);
		$user = $db_connection->real_escape_string($user);

		$db_query = 'SELECT COUNT(*) FROM `' . DB_TABLE_REVIEWS . '` WHERE `item` = UNHEX("' . $item . '") AND `user` = UNHEX("' . $user . '")';
		$db_result = $db_connection->query($db_query);

		return ((int)$db_result['COUNT(*)']) > 0;
	}
}
?>