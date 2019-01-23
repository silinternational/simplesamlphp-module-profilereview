#!/usr/bin/env bash
set -e

runny ./setup-logentries.sh

runny composer install --no-interaction --no-scripts

whenavail idp 80 200 echo idp ready, starting behat...

# Run the feature tests
./vendor/bin/behat 
