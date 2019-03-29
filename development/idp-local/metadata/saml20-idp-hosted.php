<?php

use Sil\PhpEnv\Env;
use Sil\Psr3Adapters\Psr3SamlLogger;

/**
 * SAML 2.0 IdP configuration for SimpleSAMLphp.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-hosted
 */
$metadata['http://profilereview-idp.local:8285'] = [
    /*
     * The hostname of the server (VHOST) that will use this SAML entity.
     *
     * Can be '__DEFAULT__', to use this entry by default.
     */
    'host' => 'profilereview-idp.local',

    // X.509 key and certificate. Relative to the cert directory.
    'privatekey' => 'dummy.pem',
    'certificate' => 'dummy.crt',

    /*
     * Authentication source to use. Must be one that is configured in
     * 'config/authsources.php'.
     */
    'auth' => 'example-userpass',

    /*
     * Definition of Auth Proc filter
     * ref: https://simplesamlphp.org/docs/stable/simplesamlphp-authproc
     */
    'authproc' => [
        10 => [
            'class' => 'profilereview:ProfileReview',
            'employeeIdAttr' => 'employeeNumber',
            'mfaLearnMoreUrl' => Env::get('MFA_LEARN_MORE_URL'),
            'profileUrl' => Env::get('PROFILE_URL'),
            'loggerClass' => Psr3SamlLogger::class,
        ],
    ],
];

// Copy the metadata to also work from another docker container.
$metadata['http://idp'] = [
    /*
     * The hostname of the server (VHOST) that will use this SAML entity.
     *
     * Can be '__DEFAULT__', to use this entry by default.
     */
    'host' => 'idp', // *** DIFFERENT! ***

    // X.509 key and certificate. Relative to the cert directory.
    'privatekey' => 'dummy.pem',
    'certificate' => 'dummy.crt',

    /*
     * Authentication source to use. Must be one that is configured in
     * 'config/authsources.php'.
     */
    'auth' => 'example-userpass',

    /*
     * Definition of Auth Proc filter
     * ref: https://simplesamlphp.org/docs/stable/simplesamlphp-authproc
     */
    'authproc' => [
        10 => [
            'class' => 'profilereview:ProfileReview',
            'employeeIdAttr' => 'employeeNumber',
            'mfaLearnMoreUrl' => Env::get('MFA_LEARN_MORE_URL'),
	        'profileUrl' => Env::get('PROFILE_URL_FOR_TESTS'), // *** DIFFERENT! ***
            'loggerClass' => Psr3SamlLogger::class,
        ],
    ],
];
