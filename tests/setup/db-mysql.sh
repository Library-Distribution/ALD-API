#! /usr/bin/env sh

# create the database
mysql -u root -e 'CREATE DATABASE IF NOT EXISTS `travis-test`;'

# import the individual tables
for file in MySQL/*.sql;
do
	mysql -u root travis-test < "$file"
done
