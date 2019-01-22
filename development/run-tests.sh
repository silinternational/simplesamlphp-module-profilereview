#!/usr/bin/env bash

runny ./setup-logentries.sh

runny composer install --no-interaction --no-scripts

whenavail idp 80 200 echo idp ready, starting behat...

# Run the feature tests
./vendor/bin/behat --config=features/behat.yml --strict --stop-on-failure --append-snippets
