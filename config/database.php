<?php
	# the name of the MySQL database to use
	define('DB_NAME', 'ald');

	# the name of the database server
	define('DB_SERVER', 'localhost');

	# the names of the database tables
	define('DB_TABLE_ITEMS', 'data'); # 
	define('DB_TABLE_USERS', 'users'); # 
	define('DB_TABLE_STDLIB', 'stdlib'); # 
	define('DB_TABLE_STDLIB_RELEASES', 'stdlib_releases'); # 
	define('DB_TABLE_STDLIB_PENDING', 'stdlib_pending'); # 
	define('DB_TABLE_STDLIB_ACTIONS', 'stdlib_actions'); # 

	define('DB_TABLE_CANDIDATES', 'candidates'); # 
	define('DB_TABLE_CANDIDATE_VOTING', 'candidate_voting'); # 

	define('DB_TABLE_REGISTRATION', 'registration'); # 
	define('DB_TABLE_TYPES', 'types'); # 

	define('DB_TABLE_RATINGS', 'ratings'); # 

	define('DB_TABLE_SUSPENSIONS', 'suspensions'); # 

	# the credentials for accessing the database
	define('DB_USERNAME', 'root'); # the user name
	define('DB_PASSWORD', ''); # the password
?>