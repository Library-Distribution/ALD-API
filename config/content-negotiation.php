<?php
	# The default output content type if no Accept header is given
	define('DEFAULT_MIME_TYPE', 'application/json');
	# This content type should of course be supported. For supported
	# content types, see the ContentNegotiator class.
	# To add support for a new content type, you must do the following:
	# 1) add a converter class that implements OutputConverter. for an example, see YamlConverter.php.
	# 2) require() the class in ContentNegotiator.php
	# 3) map it to the according content types in the ContentNegotiator class
?>