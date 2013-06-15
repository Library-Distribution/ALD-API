<?php

# sets whether a member of the review team can change his opinion and thus modify the review status
define('REVIEW_CAN_UPDATE', true);

# sets the minimum number of positive reviews for an item to be accepted
define('REVIEW_MIN_ACCEPTS', 3);

# sets the minimum number of negative reviews for an item to be rejected
define('REVIEW_MIN_REJECTS', 3);

# defines whether a final decision by a review admin is always required or not
define('REVIEW_ALWAYS_REQUIRE_FINAL', true);

# defines whether a final decision by a review admin is always required for a positive review
define('REVIEW_ACCEPT_REQUIRE_FINAL', true);

# defines whether a final decision by a review admin is always required for a negative review
define('REVIEW_REJECT_REQUIRE_FINAL', true);

?>