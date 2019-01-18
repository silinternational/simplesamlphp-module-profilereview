#!/usr/bin/env bash

rm -rf /data/vendor/simplesamlphp/simplesamlphp/modules/profilereview
ln -s /profilereview /data/vendor/simplesamlphp/simplesamlphp/modules/

#ln -s /profilereview/development/ssp/authsources.php /data/vendor/simplesamlphp/simplesamlphp/config/authsources.php
#ln -s /profilereview/development/ssp/saml20-idp-hosted.php /data/vendor/simplesamlphp/simplesamlphp/metadata/saml20-idp-hosted.php
#mkdir -p /data/vendor/simplesamlphp/simplesamlphp/cert/
#ln -s /profilereview/development/ssp/saml.crt /data/vendor/simplesamlphp/simplesamlphp/cert/saml.crt
#ln -s /profilereview/development/ssp/saml.pem /data/vendor/simplesamlphp/simplesamlphp/cert/saml.pem
