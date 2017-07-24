#!/bin/bash

DATA="/home/aquilax/ledger/"

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

php ${DIR}/generate.php "-1 week" today ~/ledger/ ${DIR}/../
cd ${DIR}/..
git add .
git commit --no-gpg -am "Daily update"
git push
