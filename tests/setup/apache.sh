#! /usr/bin/env bash

sudo a2enmod rewrite
sudo cp tests/setup/vhost.conf /etc/apache2/sites-enabled/default
sudo service apache2 restart

# debug:
curl -i http://localhost/.travis.yml