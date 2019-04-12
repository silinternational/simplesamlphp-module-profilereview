<?php

$config = [
    'example-userpass' => [
        'exampleauth:UserPass',
            'no_review:a' => [
                'eduPersonPrincipalName' => ['NO_REVIEW@idp'],
                'eduPersonTargetID' => ['11111111-1111-1111-1111-111111111111'],
                'sn' => ['Review'],
                'givenName' => ['No'],
                'mail' => ['no_review@example.com'],
                'employeeNumber' => ['11111'],
                'cn' => ['NO_REVIEW'],
                'mfa' => [
                    'prompt' => 'yes',
                    'add' => 'no',
                    'options' => [
                        [
                            'id' => 111,
                            'type' => 'backupcode',
                            'label' => '2SV #1',
                            'created_utc' => '2017-10-24T20:40:47Z',
                            'last_used_utc' => null,
                            'data' => [
                                'count' => 10
                            ],
                        ],
                    ],
                ],
                'method' => [
                    'add' => 'no',
                ],
                'profile_review' => 'no'
            ],
            'mfa_add:a' => [
                'eduPersonPrincipalName' => ['MFA_ADD@idp'],
                'eduPersonTargetID' => ['22222222-2222-2222-2222-222222222222'],
                'sn' => ['Add'],
                'givenName' => ['Mfa'],
                'mail' => ['mfa_add@example.com'],
                'employeeNumber' => ['22222'],
                'cn' => ['MFA_ADD'],
                'mfa' => [
                    'prompt' => 'no',
                    'add' => 'yes',
                    'options' => [],
                ],
                'method' => [
                    'add' => 'no',
                ],
                'profile_review' => 'no'
            ],
            'method_add:a' => [
                'eduPersonPrincipalName' => ['METHOD_ADD@methodidp'],
                'eduPersonTargetID' => ['44444444-4444-4444-4444-444444444444'],
                'sn' => ['Add'],
                'givenName' => ['Method'],
                'mail' => ['method_add@example.com'],
                'employeeNumber' => ['44444'],
                'cn' => ['METHOD_ADD'],
                'mfa' => [
                    'prompt' => 'yes',
                    'add' => 'no',
                    'options' => [
                        [
                            'id' => 444,
                            'type' => 'backupcode',
                            'label' => '2SV #1',
                            'created_utc' => '2017-10-24T20:40:47Z',
                            'last_used_utc' => null,
                            'data' => [
                                'count' => 10
                            ],
                        ],
                    ],
                ],
                'method' => [
                    'add' => 'yes',
                ],
                'profile_review' => 'no'
            ],
            'profile_review:a' => [
                'eduPersonPrincipalName' => ['METHOD_REVIEW@methodidp'],
                'eduPersonTargetID' => ['55555555-5555-5555-5555-555555555555'],
                'sn' => ['Review'],
                'givenName' => ['Method'],
                'mail' => ['method_review@example.com'],
                'employeeNumber' => ['55555'],
                'cn' => ['METHOD_REVIEW'],
                'mfa' => [
                    'prompt' => 'yes',
                    'add' => 'no',
                    'options' => [
                        [
                            'id' => 555,
                            'type' => 'backupcode',
                            'label' => '2SV #1',
                            'created_utc' => '2017-10-24T20:40:47Z',
                            'last_used_utc' => null,
                            'data' => [
                                'count' => 10
                            ],
                        ],
                        [
                            'id' => 556,
                            'type' => 'manager',
                            'label' => '2SV #2',
                            'created_utc' => '2017-10-24T20:40:47Z',
                            'last_used_utc' => '2017-10-24T20:41:57Z',
                            'data' => [
                            ],
                        ],
                    ],
                ],
                'method' => [
                    'add' => 'no',
                    'options' => [
                        [
                            'id' => '55555555555555555555555555555555',
                            'value' => 'method@example.com',
                            'verified' => true,
                            'created' => '2017-10-24T20:40:47Z',
                        ],
                    ],
                ],
                'profile_review' => 'yes'
            ],
        ],
    ];
