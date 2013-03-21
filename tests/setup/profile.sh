#! /usr/bin/env sh

# check if the specified profile is valid
if [ ! -d "config/profiles/$1" ]; then
    echo "$1 is not a valid config profile!"
    exit 1
fi

# overwrite config with all profile-specific config files
mmv -c -o -- "config/profiles/$1/*.php" "config/#1.php"