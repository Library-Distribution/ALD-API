#! /usr/bin/env sh

# create the database
mysql --default-character-set=latin1 -u root -e 'CREATE DATABASE IF NOT EXISTS `travis-test`;'

# import the individual tables
for file in MySQL/*.sql;
do
	mysql --default-character-set=latin1 -u root travis-test < "$file"
done

php -r '$conn = mysql_connect("localhost", "root", ""); echo "MySQL client encoding: ", mysql_client_encoding($conn), "\n\n";'