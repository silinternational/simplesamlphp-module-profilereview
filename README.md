# Profile Review simpleSAMLphp Module #
A simpleSAMLphp module for prompting the user review their profile (such as
2-factor authentication, email, etc.).

This module is implemented as an Authentication Processing Filter, 
or AuthProc. That means it can be configured in the global config.php file or 
the SP remote or IdP hosted metadata.

It is recommended to run the profilereview module at the IdP, after all
other authentication modules.

## How to use the module ##
Simply include `simplesamlphp/composer-module-installer` and this module as 
required in your `composer.json` file. The `composer-module-installer` package 
will discover this module and copy it into the `modules` folder within 
`simplesamlphp`.

You will then need to set filter parameters in your config. We recommend adding 
them to the `'authproc'` array in your `metadata/saml20-idp-hosted.php` file.

Example (for `metadata/saml20-idp-hosted.php`):

    use Sil\PhpEnv\Env;
    use Sil\Psr3Adapters\Psr3SamlLogger;
    
    // ...
    
    'authproc' => [
        10 => [
            // Required:
            'class' => 'profilereview:ProfileReview',
            'employeeIdAttr' => 'employeeNumber',
            'idBrokerAccessToken' => Env::get('ID_BROKER_ACCESS_TOKEN'),
            'idBrokerAssertValidIp' => Env::get('ID_BROKER_ASSERT_VALID_IP'),
            'idBrokerBaseUri' => Env::get('ID_BROKER_BASE_URI'),
            'idBrokerTrustedIpRanges' => Env::get('ID_BROKER_TRUSTED_IP_RANGES'),
            'mfaLearnMoreUrl' => Env::get('MFA_LEARN_MORE_URL'),
            'profileUrl' => Env::get('PROFILE_URL'),

            // Optional:
            'loggerClass' => Psr3SamlLogger::class,
        ],
        
        // ...
    ],

The `employeeIdAttr` parameter represents the SAML attribute name which has 
the user's Employee ID stored in it. In certain situations, this may be 
displayed to the user, as well as being used in log messages.

The `loggerClass` parameter specifies the name of a PSR-3 compatible class that 
can be autoloaded, to use as the logger within ExpiryDate.

The `profileUrl` parameter is for the URL of where to send the user if they
want/need to update their profile.

## Testing Locally ##

### Setup ###
Add entries to your hosts file to associate `profilereview-sp.local` and `profilereview-idp.local`
with the IP address of your docker containers.

### Automated Testing ###
Run `make test`.

### Manual Testing ###
Go to <http://profilereview-sp.local:8281/module.php/core/authenticate.php?as=profilereview-idp> in
your browser and sign in with one of the users defined in
`development/idp-local/config/authsources.php`.

Go to <http://profilereview-sp.local:8281/module.php/core/as_logout.php?ReturnTo=/&AuthId=profilereview-idp>
to logout.

## Contributing ##
To contribute, please submit issues or pull requests at 
https://github.com/silinternational/simplesamlphp-module-profilereview

## Acknowledgements ##
This is adapted from the `silinternational/simplesamlphp-module-mfa`
module, which itself is adapted from other modules. Thanks to all those who
contributed to that work.
