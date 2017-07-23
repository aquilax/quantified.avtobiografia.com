#!/bin/bash

DATA="/home/aquilax/ledger/"

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

php ${DIR}/generate.php "-1 week" today ~/ledger/ ${DIR}/../
exit
cd ${DIR}/..
git add .
git commit -am "Daily update"
git push
