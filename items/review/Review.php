<?php
require_once(dirname(__FILE__) . '/../../config/review.php');

require_once(dirname(__FILE__) . '/../../Item.php');
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

		self::TransferReview($item);
	}

	public static function HasReviewed($item, $user) {
		$db_connection = db_ensure_connection();
		$item = $db_connection->real_escape_string($item);
		$user = $db_connection->real_escape_string($user);

		$db_query = 'SELECT COUNT(*) FROM `' . DB_TABLE_REVIEWS . '` WHERE `item` = UNHEX("' . $item . '") AND `user` = UNHEX("' . $user . '")';
		$db_result = $db_connection->query($db_query);

		return ((int)$db_result['COUNT(*)']) > 0;
	}

	public static function ReviewStatus($item) {
		$db_connection = db_ensure_connection();
		$item = $db_connection->real_escape_string($item);

		$db_query = 'SELECT COUNT(*) AS count, `final`, `accept` FROM ' . DB_TABLE_REVIEWS . ' WHERE `item` = UNHEX("' . $item . '") GROUP BY `final`, `accept`';
		$db_result = $db_connection->query($db_query);

		$accept = array();
		while ($row = $db_result->fetch_assoc()) {
			if ($row['final'])
				return (bool)$row['accept']; # final decisions overwrite everything else (there must only be one of them)
			$accept[$row['accept']] = $row['count'];
		}
		if (REVIEW_ALWAYS_REQUIRE_FINAL)
			return Item::REVIEW_INDETERMINATE; # if there had been a final, it would already have exited the loop

		if ($accept[true] >= REVIEW_MIN_ACCEPTS && $accept[false] == 0 && !REVIEW_ACCEPT_REQUIRE_FINAL)
			return Item::REVIEW_GOOD; # accepted based on the current (non-final) accepts
		else if ($accept[false] >= REVIEW_MIN_REJECTS && $accept[true] == 0 && !REVIEW_REJECT_REQUIRE_FINAL)
			return Item::REVIEW_BAD; # rejected based on the current (no-final) rejects

		return Item::REVIEW_INDETERMINATE; # must still be in review
	}

	public static function TransferReview($item) {
		$status = self::ReviewStatus($item);
		if ($status !== Item::REVIEW_INDETERMINATE) {
			Item::Review($item, $status);
		}
	}
}
?>