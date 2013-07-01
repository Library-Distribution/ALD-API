#! /usr/bin/env bash

sudo a2enmod rewrite headers php5
sudo cp tests/setup/vhost.conf /etc/apache2/sites-enabled/default

TRAVIS_BUILD_DIR="${TRAVIS_BUILD_DIR//\//\\/}" # escape for below
sudo sed -i -e "s/TRAVIS_BUILD_DIR/'$TRAVIS_BUILD_DIR'/g" /etc/apache2/sites-enabled/default

sudo service apache2 restart
