#!/usr/bin/env bash

if [ -d "/data/vendor/simplesamlphp/simplesamlphp/modules/profilereview" ]; then
  exit
fi

ln -s /profilereview /data/vendor/simplesamlphp/simplesamlphp/modules/
