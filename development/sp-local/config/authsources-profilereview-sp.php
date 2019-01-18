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
        'entityID' => 'http://profilereview-sp.local:8281',
        'idp' => 'http://profilereview-idp.local:8285',
        'discoURL' => null,
        'NameIDPolicy' => "urn:oasis:names:tc:SAML:2.0:nameid-format:persistent",
    ],

    'profilereview-idp-no-port' => [
        'saml:SP',
        'entityID' => 'http://sp',
        'idp' => 'http://idp',
        'discoURL' => null,
        'NameIDPolicy' => "urn:oasis:names:tc:SAML:2.0:nameid-format:persistent",
    ],
];
