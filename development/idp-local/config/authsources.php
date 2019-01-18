<?php

$config = [
    'example-userpass' => [
        'exampleauth:UserPass',
            'no_review:a' => [
                'eduPersonPrincipalName' => ['NO_REVIEW@mfaidp'],
                'eduPersonTargetID' => ['11111111-1111-1111-1111-111111111111'],
                'sn' => ['Review'],
                'givenName' => ['No'],
                'mail' => ['no_review@example.com'],
                'employeeNumber' => ['11111'],
                'cn' => ['NO_REVIEW'],
                'mfa' => [
                    'prompt' => 'yes',
                    'add' => 'no',
                    'review' => 'no',
                    'options' => [
                        'id' => 345,
                        'type' => 'backupcode',
                        'label' => '2SV #1',
                        'created_utc' => '2017-10-24T20:40:47Z',
                        'last_used_utc' => null,
                        'data' => [
                            'count' => 10
                        ],
                    ],
                ],
                'method' => [
                    'add' => 'no',
                    'review' => 'no',
                ],
            ],
            'mfa_add:a' => [
                'eduPersonPrincipalName' => ['MFA_ADD@mfaidp'],
                'eduPersonTargetID' => ['11111111-1111-1111-1111-111111111111'],
                'sn' => ['Add'],
                'givenName' => ['Mfa'],
                'mail' => ['mfa_add@example.com'],
                'employeeNumber' => ['11111'],
                'cn' => ['MFA_ADD'],
                'mfa' => [
                    'prompt' => 'no',
                    'add' => 'yes',
                    'review' => 'no',
                    'options' => [],
                ],
                'method' => [
                    'add' => 'no',
                    'review' => 'no',
                ],
            ],
        ],
    ];
