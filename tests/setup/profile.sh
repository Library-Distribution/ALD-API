#! /usr/bin/env sh

# check if the specified profile is valid
if [ ! -d "config/profiles/$1" ]; then
    echo "$1 is not a valid config profile!"
    exit 1
fi

echo "Using config profile \"$1\"..."

# overwrite config with all profile-specific config files
for file in config/profiles/$1/*.php;
do
	base=${file##*/}
	cp -v -f $file config/$base | sed 's/^/    /'
done
echo "\n\n"