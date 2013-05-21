
echo "Installing PHP packages..."
if [ "$TRAVIS_PHP_VERSION" = "5.5" ]; then
	echo "	Using pyrus..."
	pyrus channel-discover pear.phpunit.de
	pyrus install phpunit/DbUnit
else
	echo "	Using pear..."
	pear channel-discover pear.phpunit.de
	pear install phpunit/DbUnit
fi
