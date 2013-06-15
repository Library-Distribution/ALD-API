<?php
# Whether or not downgrading a lib in the stdlib to a previous version (in a new stdlib release) is allowed
define('STDLIB_ALLOW_DOWNGRADE', true);

# The item types allowed in the stdlib, separated by \0
define('STDLIB_ALLOWED_TYPES', 'lib');

# The minimum number of accepts a candidate needs to be accepted
define('CANDIDATE_MIN_ACCEPTS', 3);

# The minimum number of rejects a candidate can receive before it is considered rejected
define('CANDIDATE_MIN_REJECTS', 3);

# Whether a "final" decision by the stdlib admin is always required or not
define('CANDIDATE_ALWAYS_REQUIRE_FINAL', true);
# Regardless of this setting, a final decision is required if there are contradicting ratings by stdlib moderators.

# Whether a final decision by the stdlib admin is always required for acceptions or not
define('CANDIDATE_ACCEPT_REQUIRE_FINAL', true);
# This setting is overwritten by CANDIDATE_ALWAYS_REQUIRE_FINAL.

# Whether a final decision by the stdlib admin is always required for rejections or not
define('CANDIDATE_REJECT_REQUIRE_FINAL', true);
# This setting is overwritten by CANDIDATE_ALWAYS_REQUIRE_FINAL.
?>