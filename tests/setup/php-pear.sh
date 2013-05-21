
echo "Installing PHP packages..."
if [ "$TRAVIS_PHP_VERSION" = "5.5" ]; then
	echo "    Using pyrus..."
	pyrus channel-discover pear.phpunit.de | sed 's/^/        /'
	pyrus install phpunit/DbUnit | sed 's/^/        /'
else
	echo "    Using pear..."
	pear channel-discover pear.phpunit.de | sed 's/^/        /'
	pear install phpunit/DbUnit | sed 's/^/        /'
fi
