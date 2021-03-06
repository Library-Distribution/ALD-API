#! /usr/bin/env sh

echo "Setting up MySQL for the tests..."

# create the database
echo "    Creating the database..."
mysql --default-character-set=utf8 -u root -e 'CREATE DATABASE IF NOT EXISTS `travis-test`;'

# import the individual tables
echo "    Importing the DB tables..."
for file in MySQL/*.sql;
do
	echo "        $file"
	mysql --default-character-set=utf8 -u root travis-test < "$file"
done
