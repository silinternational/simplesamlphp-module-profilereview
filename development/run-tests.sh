#!/usr/bin/env bash

runny composer install --no-interaction --no-scripts

whenavail idp 80 200 echo idp ready, starting behat...

# Run the feature tests
./vendor/bin/behat --format-settings='{"expand": true}'

# Example command line to run a single test
#./vendor/bin/behat --format-settings='{"expand": true}' features/profilereview.feature:17
