<?php
	# the time interval after which a registration request times out
	define('REGISTRATION_TIMEOUT', '30 MINUTE');
	# The value must follow the rules of the MySQL DATE_ADD() function (`expr` followed by `unit`)
	# (http://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_date-add).

	# a list of URLs to POST data to after a successful registration, separated by space
	define('POST_REGISTRATION_URLS', '');
	# each of these URLs receives the following POST data:
	#   + user name ('user')
	#   + user id ('id')
	#   + mail address ('mail')
	# (The words in brackets are the names of the actual POST parameters.)

	# a Perl Compatible Regular Expression (PCRE) that is used to validate a user name
	define('USER_NAME_REGEX', '/^\w[\w\[\]\-\.\|\$\§\<\>]{2,25}$/i');
	# If a user name does not fit this RegEx, the request to initiate a registration is ans-
	# wered with a '403 - Forbidden' status code.
	#
	# Notes:
	#   + When the maximum length is increased, the database fields must be adjusted accordingly.
	#     This affects the 'DB_TABLE_USERS' database table and the 'DB_TABLE_REGISTRATION' table.

	# a list of user names which must not be registered
	define('FORBIDDEN_USER_NAMES', "");
	# This is a list of user names which fit the USER_NAME_REGEX, but must still not be registered.
	# Separate the names by NUL bytes ('\0').
	#
	# Notes:
	#   + remember that the '\0' escape sequence requires double quotes

	# a list of user names which can not be registered by the public
	define('RESERVED_USER_NAMES', "");
	# The only difference between this and FORBIDDEN_USER_NAMES is that the names
	# in this list may be registered by users which have the PRIVILEGE_REGISTRATION
	# but not by anyone else.
	#
	# Notes:
	#   + as above, separate the names by '\0' and use double quotes

	# defines whether registration is open to public or not
	define('PUBLIC_REGISTRATION', true);
	# If this is set to `true`, any internet user can register himself to the site.
	# Otherwise, only users who are already registered and have the PRIVILEGE_REGISTRATION
	# privilege can start a registration. In this case, such a user initiates a registration
	# with some name, mail and password. The user to be registered receives the mail and can
	# then complete the registration.
	#
	# Notes:
	#   + the password should in this case be included in the mail
	#   + the REGISTRATION_TIMEOUT should be configured in a way which allows cooperation e.g. between different timezones.

	# the subject for the mail sent to the registering user
	define('REGISTRATION_MAIL_SUBJECT', 'Confirm your registration');

	# the template for the mail to be sent to the registering user
	define('REGISTRATION_MAIL_TEMPLATE', '');
	# When registering, a mail is sent to the new user to validate he's a human and he owns the specified email address.
	# This template can contain the following variables:
	# * {$NAME} - the name to be registered
	# * {$MAIL} - the mail address the mail is sent to
	# * {$PASSWORD} - the password specified
	# * {$ID} - the ID of the registration session, required to complete the registration

	# the sender mail address to send registration verification mails from
	define('REGISTRATION_MAIL_SENDER', '');
?>