<?php
	# defines whether uploads are generally allowed or not
	define('ENABLE_UPLOAD', true);
	# If uploads are disabled, a request to upload a file
	# will result in a 403 error code.

	# the maximum upload size for a file in bytes
	define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); # default: 10 MB
	# Files which are too large will return a 413 error code.
	#
	# Notes:
	#    - When uploading via web form, it is recommended to add a MAX_FILE_SIZE field.
	#    - Also remember setting 'upload_max_filesize' and 'post_max_size' in  your php.ini

	# the folder to store files in
	define('UPLOAD_FOLDER', dirname(dirname(__FILE__)) . '/uploads/'); # connected to .gitignore
	# The path must be absolute, with trailing slash.
	#
	# Notes:
	#    - When changing this value, remember moving the old files.
?>