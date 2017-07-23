#!/bin/bash

DATA="/home/aquilax/ledger/"

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

php ${DIR}/generate.php "1 june 2017" today ~/ledger/ ${DIR}/../content/
cd ${DIR}/..
git add .
git commit -am "Daily update"
git push
