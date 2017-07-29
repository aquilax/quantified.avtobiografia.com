#!/bin/bash

DATA="/home/aquilax/ledger/"

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

php ${DIR}/generate.php "- 1 week" today ${DATA} ${DIR}/../
find ${DIR}/../static/photos/ -type f -mtime -3 -iname "*.jpg" -exec mogrify -verbose -format jpg -layers Dispose -resize 800x800 -quality 75% {} \;
cd ${DIR}/..
git add .
git commit --no-gpg -am "Daily update"
git push
