<?php

$config = [

    // This is a authentication source which handles admin authentication.
    'admin' => [
        // The default is to use core:AdminPassword, but it can be replaced with
        // any authentication source.

        'core:AdminPassword',
    ],

    'profilereview-idp' => [
        'saml:SP',
        'entityID' => 'http://profilereview-pw-manager.local:52042',
        'idp' => 'http://profilereview-idp.local:52040',
        'discoURL' => null,
        'NameIDPolicy' => "urn:oasis:names:tc:SAML:2.0:nameid-format:persistent",
    ],

    'profilereview-idp-no-port' => [
        'saml:SP',
        'entityID' => 'http://pwmanager',
        'idp' => 'http://idp',
        'discoURL' => null,
        'NameIDPolicy' => "urn:oasis:names:tc:SAML:2.0:nameid-format:persistent",
    ],
];
