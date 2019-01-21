#!/usr/bin/env bash

if [ -d "/data/vendor/simplesamlphp/simplesamlphp/modules/profilereview" ]; then
  mv /data/vendor/simplesamlphp/simplesamlphp/modules/profilereview /data/vendor/simplesamlphp/simplesamlphp/modules/profilereview#
  rm /data/vendor/simplesamlphp/simplesamlphp/modules/profilereview#/default-enable
fi

ln -s /profilereview /data/vendor/simplesamlphp/simplesamlphp/modules/
