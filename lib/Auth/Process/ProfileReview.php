<?php

use Psr\Log\LoggerInterface;
use Sil\PhpEnv\Env;
use Sil\Idp\IdBroker\Client\exceptions\MfaRateLimitException;
use Sil\Idp\IdBroker\Client\IdBrokerClient;
use Sil\Psr3Adapters\Psr3SamlLogger;
use Sil\SspProfileReview\LoggerFactory;
use Sil\SspProfileReview\LoginBrowser;
use SimpleSAML\Utils\HTTP;

/**
 * Filter which prompts the user for profile review.
 *
 * See README.md for sample (and explanation of) expected configuration.
 */
class sspmod_profilereview_Auth_Process_ProfileReview extends SimpleSAML_Auth_ProcessingFilter
{
    const SESSION_TYPE = 'profilereview';
    const STAGE_SENT_TO_XXX = 'mfa:sent_to_xxx';

    private $employeeIdAttr = null;
    private $mfaLearnMoreUrl = null;
    private $profileUrl = null;
    
    private $idBrokerAccessToken = null;
    private $idBrokerAssertValidIp;
    private $idBrokerBaseUri = null;
    private $idBrokerClientClass = null;
    private $idBrokerTrustedIpRanges = [];
    
    /** @var LoggerInterface */
    protected $logger;
    
    /** @var string */
    protected $loggerClass;
    
    /**
     * Initialize this filter.
     *
     * @param array $config  Configuration information about this filter.
     * @param mixed $reserved  For future use.
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        $this->initComposerAutoloader();
        assert('is_array($config)');
        
        $this->loggerClass = $config['loggerClass'] ?? Psr3SamlLogger::class;
        $this->logger = LoggerFactory::get($this->loggerClass);
        
        $this->loadValuesFromConfig($config, [
            'profileUrl',
            'employeeIdAttr',
            'idBrokerAccessToken',
            'idBrokerBaseUri',
        ]);
        
        $tempTrustedIpRanges = $config['idBrokerTrustedIpRanges'] ?? '';
        if (! empty($tempTrustedIpRanges)) {
            $this->idBrokerTrustedIpRanges = explode(',', $tempTrustedIpRanges);
        }
        $this->idBrokerAssertValidIp = (bool)($config['idBrokerAssertValidIp'] ?? true);
        $this->idBrokerClientClass = $config['idBrokerClientClass'] ?? IdBrokerClient::class;
    }
    
    protected function loadValuesFromConfig($config, $attributes)
    {
        foreach ($attributes as $attribute) {
            $this->$attribute = $config[$attribute] ?? null;
            
            self::validateConfigValue(
                $attribute,
                $this->$attribute,
                $this->logger
            );
        }
    }
    
    /**
     * Validate the given config value
     *
     * @param string $attribute The name of the attribute.
     * @param mixed $value The value to check.
     * @param LoggerInterface $logger The logger.
     * @throws Exception
     */
    public static function validateConfigValue($attribute, $value, $logger)
    {
        if (empty($value) || !is_string($value)) {
            $exception = new Exception(sprintf(
                'The value we have for %s (%s) is empty or is not a string',
                $attribute,
                var_export($value, true)
            ), 1507146042);

            $logger->critical($exception->getMessage());
            throw $exception;
        }
    }
    
    /**
     * Get the specified attribute from the given state data.
     *
     * NOTE: If the attribute's data is an array, the first value will be
     *       returned. Otherwise, the attribute's data will simply be returned
     *       as-is.
     *
     * @param string $attributeName The name of the attribute.
     * @param array $state The state data.
     * @return mixed The attribute value, or null if not found.
     */
    protected function getAttribute($attributeName, $state)
    {
        $attributeData = $state['Attributes'][$attributeName] ?? null;
        
        if (is_array($attributeData)) {
            return $attributeData[0] ?? null;
        }
        
        return $attributeData;
    }
    
    /**
     * Get all of the values for the specified attribute from the given state
     * data.
     *
     * NOTE: If the attribute's data is an array, it will be returned as-is.
     *       Otherwise, it will be returned as a single-entry array of the data.
     *
     * @param string $attributeName The name of the attribute.
     * @param array $state The state data.
     * @return array|null The attribute's value(s), or null if the attribute was
     *     not found.
     */
    protected function getAttributeAllValues($attributeName, $state)
    {
        $attributeData = $state['Attributes'][$attributeName] ?? null;
        
        return is_null($attributeData) ? null : (array)$attributeData;
    }
    
    /**
     * Get an ID Broker client.
     *
     * @param array $idBrokerConfig
     * @return IdBrokerClient
     */
    protected static function getIdBrokerClient($idBrokerConfig)
    {
        $clientClass = $idBrokerConfig['clientClass'];
        $baseUri = $idBrokerConfig['baseUri'];
        $accessToken = $idBrokerConfig['accessToken'];
        $trustedIpRanges = $idBrokerConfig['trustedIpRanges'];
        $assertValidIp = $idBrokerConfig['assertValidIp'];
        
        return new $clientClass($baseUri, $accessToken, [
            'http_client_options' => [
                'timeout' => 10,
            ],
            IdBrokerClient::TRUSTED_IPS_CONFIG => $trustedIpRanges,
            IdBrokerClient::ASSERT_VALID_BROKER_IP_CONFIG => $assertValidIp,
        ]);
    }
    
    /**
     * Get the MFA type to use based on the available options.
     *
     * @param array[] $mfaOptions The available MFA options.
     * @param int $mfaId The ID of the desired MFA option.
     * @return array The MFA option to use.
     * @throws \InvalidArgumentException
     */
    public static function getMfaOptionById($mfaOptions, $mfaId)
    {
        if (empty($mfaId)) {
            throw new \Exception('No MFA ID was provided.');
        }
        
        foreach ($mfaOptions as $mfaOption) {
            if ((int)$mfaOption['id'] === (int)$mfaId) {
                return $mfaOption;
            }
        }
        
        throw new \Exception(
            'No MFA option has an ID of ' . var_export($mfaId, true)
        );
    }
    
    /**
     * Get the MFA type to use based on the available options.
     *
     * @param array[] $mfaOptions The available MFA options.
     * @param string $userAgent The User-Agent sent by the user's browser, used
     *     for detecting U2F support.
     * @return array The MFA option to use.
     * @throws \InvalidArgumentException
     */
    public static function getMfaOptionToUse($mfaOptions, $userAgent)
    {
        if (empty($mfaOptions)) {
            throw new \Exception('No MFA options were provided.');
        }
        
        if (LoginBrowser::supportsU2f($userAgent)) {
            $mfaTypePriority = ['u2f', 'totp', 'backupcode'];
        } else {
            $mfaTypePriority = ['totp', 'backupcode', 'u2f'];
        }
        
        foreach ($mfaTypePriority as $mfaType) {
            foreach ($mfaOptions as $mfaOption) {
                if ($mfaOption['type'] === $mfaType) {
                    return $mfaOption;
                }
            }
        }

        return $mfaOptions[0];
    }
    
    /**
     * Get the number of backup codes that the user had left PRIOR to this login.
     *
     * @param array $mfaOptions The list of MFA options.
     * @return int The number of backup codes that the user HAD (prior to this
     *     login).
     */
    public static function getNumBackupCodesUserHad(array $mfaOptions)
    {
        $numBackupCodes = 0;
        foreach ($mfaOptions as $mfaOption) {
            $mfaType = $mfaOption['type'] ?? null;
            if ($mfaType === 'backupcode') {
                $numBackupCodes += intval($mfaOption['data']['count'] ?? 0);
            }
        }
        
        return $numBackupCodes;
    }
    
    /**
     * Get the template identifier (string) to use for the specified MFA type.
     *
     * @param string $mfaType The desired MFA type, such as 'u2f', 'totp', or
     *     'backupcode'.
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function getTemplateFor($mfaType)
    {
        $mfaOptionTemplates = [
            'backupcode' => 'mfa:prompt-for-mfa-backupcode.php',
            'totp' => 'mfa:prompt-for-mfa-totp.php',
            'u2f' => 'mfa:prompt-for-mfa-u2f.php',
            'manager' => 'mfa:prompt-for-mfa-manager.php',
        ];
        $template = $mfaOptionTemplates[$mfaType] ?? null;
        
        if ($template === null) {
            throw new \InvalidArgumentException(sprintf(
                'No %s MFA template is available.',
                var_export($mfaType, true)
            ), 1507219338);
        }
        return $template;
    }
    
    /**
     * Return the saml:RelayState if it begins with "http" or "https". Otherwise
     * return an empty string.
     *
     * @param array $state
     * @returns string
     */
    protected static function getRelayStateUrl($state)
    {
        if (array_key_exists('saml:RelayState', $state)) {
            $samlRelayState = $state['saml:RelayState'];
            
            if (strpos($samlRelayState, "http://") === 0) {
                return $samlRelayState;
            }

            if (strpos($samlRelayState, "https://") === 0) {
                return $samlRelayState;
            }
        }
        return '';
    }
    
    /**
     * Get new Printable Backup Codes for the user, then redirect the user to a
     * page showing the user their new codes.
     *
     * NOTE: This function never returns.
     *
     * @param array $state The state data.
     * @param LoggerInterface $logger A PSR-3 compatible logger.
     */
    public static function giveUserNewBackupCodes(array &$state, $logger)
    {
        try {
            $idBrokerClient = self::getIdBrokerClient($state['idBrokerConfig']);
            $newMfaRecord = $idBrokerClient->mfaCreate(
                $state['employeeId'],
                'backupcode'
            );
            $newBackupCodes = $newMfaRecord['data'];
            
            $logger->warning(json_encode([
                'event' => 'New backup codes result: succeeded',
                'employeeId' => $state['employeeId'],
            ]));
        } catch (\Throwable $t) {
            $logger->error(json_encode([
                'event' => 'New backup codes result: failed',
                'employeeId' => $state['employeeId'],
                'error' => $t->getCode() . ': ' . $t->getMessage(),
            ]));
        }
        
        $state['newBackupCodes'] = $newBackupCodes ?? null;
        $stateId = SimpleSAML_Auth_State::saveState($state, self::STAGE_SENT_TO_NEW_BACKUP_CODES_PAGE);
        $url = SimpleSAML\Module::getModuleURL('mfa/new-backup-codes.php');
        
        HTTP::redirectTrustedURL($url, ['StateId' => $stateId]);
    }
    
    protected static function hasMfaOptions($mfa)
    {
        return (count($mfa['options']) > 0);
    }
    
    /**
     * See if the user has any MFA options other than the specified type.
     *
     * @param string $excludedMfaType
     * @param array $state
     * @return bool
     */
    public static function hasMfaOptionsOtherThan($excludedMfaType, $state)
    {
        $mfaOptions = $state['mfaOptions'] ?? [];
        foreach ($mfaOptions as $mfaOption) {
            if (strval($mfaOption['type']) !== strval($excludedMfaType)) {
                return true;
            }
        }
        return false;
    }
    
    protected function initComposerAutoloader()
    {
        $path = __DIR__ . '/../../../vendor/autoload.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
    
    protected static function isHeadedToProfileUrl($state, $ProfileUrl)
    {
        if (array_key_exists('saml:RelayState', $state)) {
            $currentDestination = self::getRelayStateUrl($state);
            if (! empty($currentDestination)) {
                return (strpos($currentDestination, $ProfileUrl) === 0);
            }
        }
        return false;
    }
    
    /**
     * Validate the given MFA submission. If successful, this function
     * will NOT return. If the submission does not pass validation, an error
     * message will be returned.
     *
     * @param int $mfaId The ID of the MFA option used.
     * @param string $employeeId The Employee ID that this MFA option belongs to.
     * @param string $mfaSubmission The value of the MFA submission.
     * @param array $state The array of state information.
     * @param bool $rememberMe Whether or not to set remember me cookies
     * @param LoggerInterface $logger A PSR-3 compatible logger.
     * @param string $mfaType The type of the MFA ('u2f', 'totp', 'backupcode').
     * @return void|string If validation fails, an error message to show to the
     *     end user will be returned.
     */
    public static function validateMfaSubmission(
        $mfaId,
        $employeeId,
        $mfaSubmission,
        $state,
        $rememberMe,
        LoggerInterface $logger,
        string $mfaType
    ) {
        if (empty($mfaId)) {
            return 'No MFA ID was provided.';
        } elseif (empty($employeeId)) {
            return 'No Employee ID was provided.';
        } elseif (empty($mfaSubmission)) {
            return 'No MFA submission was provided.';
        }
        
        try {
            $idBrokerClient = self::getIdBrokerClient($state['idBrokerConfig']);
            $validMfa = $idBrokerClient->mfaVerify(
                $mfaId,
                $employeeId,
                $mfaSubmission
            );
            if (! $validMfa) {
                if ($mfaType === 'backupcode') {
                    return 'Incorrect 2-step verification code. Printable backup codes can only be used once, please try a different code.';
                }
                return 'Incorrect 2-step verification code.';
            }
        } catch (\Throwable $t) {
            if ($t instanceof MfaRateLimitException) {
                $logger->error(json_encode([
                    'event' => 'MFA is rate-limited',
                    'employeeId' => $employeeId,
                    'mfaId' => $mfaId,
                    'mfaType' => $mfaType,
                ]));
                return 'There have been too many wrong answers recently. '
                     . 'Please wait a minute, then try again.';
            }
            
            $logger->critical($t->getCode() . ': ' . $t->getMessage());
            return 'Something went wrong while we were trying to do the '
                 . '2-step verification.';
        }

        // Set remember me cookies if requested
        if ($rememberMe) {
            self::setRememberMeCookies($state['employeeId'], $state['mfaOptions']);
        }
        
        $logger->warning(json_encode([
            'event' => 'MFA validation result: success',
            'employeeId' => $employeeId,
            'mfaType' => $mfaType,
        ]));
        
        // Handle situations where the user is running low on backup codes.
        if ($mfaType === 'backupcode') {
            $numBackupCodesUserHad = self::getNumBackupCodesUserHad(
                $state['mfaOptions'] ?? []
            );
            $numBackupCodesRemaining = $numBackupCodesUserHad - 1;
            
            if ($numBackupCodesRemaining <= 0) {
                self::redirectToOutOfBackupCodesMessage($state, $employeeId);
                throw new \Exception('Failed to send user to out-of-backup-codes page.');
            } elseif ($numBackupCodesRemaining < 4) {
                self::redirectToLowOnBackupCodesNag(
                    $state,
                    $employeeId,
                    $numBackupCodesRemaining
                );
                throw new \Exception('Failed to send user to low-on-backup-codes page.');
            }
        }
        
        //unset($state['Attributes']['mfa']);
        // The following function call will never return.
        SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
        throw new \Exception('Failed to resume processing auth proc chain.');
    }
    
    /**
     * Redirect the user to set up MFA.
     *
     * @param array $state
     */
    public static function redirectToProfile(&$state)
    {
        $ProfileUrl = $state['ProfileUrl'];
        
        // Tell the MFA-setup URL where the user is ultimately trying to go (if known).
        $currentDestination = self::getRelayStateUrl($state);
        if (! empty($currentDestination)) {
            $ProfileUrl = SimpleSAML\Utils\HTTP::addURLParameters(
                $ProfileUrl,
                ['returnTo' => $currentDestination]
            );
        }
        
        $logger = LoggerFactory::getAccordingToState($state);
        $logger->warning(sprintf(
            'mfa: Sending Employee ID %s to set up MFA at %s',
            var_export($state['employeeId'] ?? null, true),
            var_export($ProfileUrl, true)
        ));
        
        HTTP::redirectTrustedURL($ProfileUrl);
    }
    
    /**
     * Apply this AuthProc Filter. It will either return (indicating that it
     * has completed) or it will redirect the user, in which case it will
     * later call `SimpleSAML_Auth_ProcessingChain::resumeProcessing($state)`.
     *
     * @param array &$state The current state.
     */
    public function process(&$state)
    {
        // Get the necessary info from the state data.
        $employeeId = $this->getAttribute($this->employeeIdAttr, $state);
        $mfa = $this->getAttributeAllValues('mfa', $state);
        $isHeadedToProfileUrl = self::isHeadedToProfileUrl(
            $state,
            $this->profileUrl
        );
        
        // Record to the state what logger class to use.
        $state['loggerClass'] = $this->loggerClass;
        
        // Add to the state any config data we may need for the low-on/out-of
        // backup codes pages.
        $state['ProfileUrl'] = $this->profileUrl;

        if (self::shouldNagToSetUpMfa($mfa)) {
            if ($isHeadedToProfileUrl) {
                return;
            }
            
            $this->redirectToMfaNag($state, $employeeId, $this->profileUrl);
            return;
        }
    }
    
    /**
     * Redirect the user to a page telling them they must set up MFA.
     *
     * @param array $state The state data.
     * @param string $employeeId The Employee ID of the user account.
     * @param string $mfaSetupUrl URL to MFA setup process
     */
    protected function redirectToMfaNeededMessage(&$state, $employeeId, $mfaSetupUrl)
    {
        assert('is_array($state)');
        
        $this->logger->info(sprintf(
            'mfa: Redirecting Employee ID %s to must-set-up-MFA message.',
            var_export($employeeId, true)
        ));
        
        /* Save state and redirect. */
        $state['employeeId'] = $employeeId;
        $state['mfaLearnMoreUrl'] = $this->mfaLearnMoreUrl;
        $state['mfaSetupUrl'] = $mfaSetupUrl;
        
        $stateId = SimpleSAML_Auth_State::saveState($state, self::STAGE_SENT_TO_MFA_NEEDED_MESSAGE);
        $url = SimpleSAML\Module::getModuleURL('mfa/must-set-up-mfa.php');
        
        HTTP::redirectTrustedURL($url, ['StateId' => $stateId]);
    }

    /**
     * Redirect user to nag page encouraging them to setup MFA
     *
     * @param array $state The state data.
     * @param string $employeeId The Employee ID of the user account.
     * @param string $ProfileUrl URL to MFA setup process
     */
    protected function redirectToMfaNag(&$state, $employeeId, $ProfileUrl)
    {
        assert('is_array($state)');

        $this->logger->info(sprintf(
            'mfa: Redirecting Employee ID %s to MFA nag message.',
            var_export($employeeId, true)
        ));

        /* Save state and redirect. */
        $state['employeeId'] = $employeeId;
        $state['mfaLearnMoreUrl'] = $this->mfaLearnMoreUrl;
        $state['ProfileUrl'] = $ProfileUrl;

        $stateId = SimpleSAML_Auth_State::saveState($state, self::STAGE_SENT_TO_MFA_NAG);
        $url = SimpleSAML\Module::getModuleURL('mfa/nag-for-mfa.php');

        HTTP::redirectTrustedURL($url, array('StateId' => $stateId));
    }
    
    /**
     * Redirect the user to the appropriate MFA-prompt page.
     *
     * @param array $state The state data.
     * @param string $employeeId The Employee ID of the user account.
     * @param array $mfaOptions Array of MFA options
     */
    protected function redirectToMfaPrompt(&$state, $employeeId, $mfaOptions)
    {
        assert('is_array($state)');
        
        /** @todo Check for valid remember-me cookies here rather doing a redirect first. */
        
        $state['mfaOptions'] = $mfaOptions;
        $state['idBrokerConfig'] = [
            'accessToken' => $this->idBrokerAccessToken,
            'assertValidIp' => $this->idBrokerAssertValidIp,
            'baseUri' => $this->idBrokerBaseUri,
            'clientClass' => $this->idBrokerClientClass,
            'trustedIpRanges' => $this->idBrokerTrustedIpRanges,
        ];
        
        $this->logger->info(sprintf(
            'mfa: Redirecting Employee ID %s to MFA prompt.',
            var_export($employeeId, true)
        ));
        
        /* Save state and redirect. */
        $state['employeeId'] = $employeeId;
        
        $id = SimpleSAML_Auth_State::saveState($state, self::STAGE_SENT_TO_MFA_PROMPT);
        $url = SimpleSAML\Module::getModuleURL('mfa/prompt-for-mfa.php');

        $mfaOption = self::getMfaOptionToUse($mfaOptions, LoginBrowser::getUserAgent());
        
        HTTP::redirectTrustedURL($url, [
            'mfaId' => $mfaOption['id'],
            'StateId' => $id,
        ]);
    }

    /**
     * Validate that remember me cookie values are legit and valid
     * @param string $cookieHash
     * @param string $expireDate
     * @param $mfaOptions
     * @param $state
     * @return bool
     */
    public static function isRememberMeCookieValid(
        string $cookieHash,
        string $expireDate,
        $mfaOptions,
        $state
    ): bool {
        $rememberSecret = Env::requireEnv('REMEMBER_ME_SECRET');
        if (! empty($cookieHash) && ! empty($expireDate) && is_numeric($expireDate)) {
            // Check if value of expireDate is in future
            if ((int)$expireDate > time()) {
                $expectedString = self::generateRememberMeCookieString($rememberSecret, $state['employeeId'], $expireDate, $mfaOptions);
                return password_verify($expectedString, $cookieHash);
            }
        }

        return false;
    }

    /**
     * Generate and return a string to be hashed for remember me cookie
     * @param string $rememberSecret
     * @param string $employeeId
     * @param int $expireDate
     * @param array $mfaOptions
     * @return string
     */
    public static function generateRememberMeCookieString(
        string $rememberSecret,
        string $employeeId,
        int $expireDate,
        array $mfaOptions
    ): string {
        $allMfaIds = '';
        foreach ($mfaOptions as $opt) {
            $allMfaIds .= $opt['id'];
        }

        $string = $rememberSecret . $employeeId . $expireDate . $allMfaIds;
        return $string;
    }
    
    /**
     * Redirect the user to a page telling them they are running low on backup
     * codes and encouraging them to create more now.
     *
     * NOTE: This function never returns.
     *
     * @param array $state The state data.
     * @param string $employeeId The Employee ID of the user account.
     * @param int $numBackupCodesRemaining The number of backup codes that the
     *     user has left (now that they have used up one for this login).
     */
    protected static function redirectToLowOnBackupCodesNag(
        array &$state,
        $employeeId,
        $numBackupCodesRemaining
    ) {
        $state['employeeId'] = $employeeId;
        $state['numBackupCodesRemaining'] = $numBackupCodesRemaining;
        
        $stateId = SimpleSAML_Auth_State::saveState($state, self::STAGE_SENT_TO_LOW_ON_BACKUP_CODES_NAG);
        $url = SimpleSAML\Module::getModuleURL('mfa/low-on-backup-codes.php');
        
        HTTP::redirectTrustedURL($url, ['StateId' => $stateId]);
    }
    
    /**
     * Redirect the user to a page telling them they just used up their last
     * backup code.
     *
     * NOTE: This function never returns.
     *
     * @param array $state The state data.
     * @param string $employeeId The Employee ID of the user account.
     */
    protected static function redirectToOutOfBackupCodesMessage(array &$state, $employeeId)
    {
        $state['employeeId'] = $employeeId;
        
        $stateId = SimpleSAML_Auth_State::saveState($state, self::STAGE_SENT_TO_OUT_OF_BACKUP_CODES_MESSAGE);
        $url = SimpleSAML\Module::getModuleURL('mfa/out-of-backup-codes.php');
        
        HTTP::redirectTrustedURL($url, ['StateId' => $stateId]);
    }

    /**
     * Set cookies c1 and c2
     * @param string $employeeId
     * @param array $mfaOptions
     * @param string $rememberDuration
     */
    public static function setRememberMeCookies(
        string $employeeId,
        array $mfaOptions,
        string $rememberDuration = '+30 days'
    ) {
        $rememberSecret = Env::requireEnv('REMEMBER_ME_SECRET');
        $secureCookie = Env::get('SECURE_COOKIE', true);
        $expireDate = strtotime($rememberDuration);
        $cookieString = self::generateRememberMeCookieString($rememberSecret, $employeeId, $expireDate, $mfaOptions);
        $cookieHash = password_hash($cookieString, PASSWORD_DEFAULT);
        setcookie('c1', base64_encode($cookieHash), $expireDate, '/', null, $secureCookie, true);
        setcookie('c2', $expireDate, $expireDate, '/', null, $secureCookie, true);
    }
    
    protected static function shouldNagToSetUpMfa($mfa)
    {
        return (strtolower($mfa['add']) === 'yes');
    }
    
    /**
     * Send a rescue code to the manager, then redirect the user to a page where they
     * can enter the code.
     *
     * NOTE: This function never returns.
     *
     * @param array $state The state data.
     * @param LoggerInterface $logger A PSR-3 compatible logger.
     */
    public static function sendManagerCode(array &$state, $logger)
    {
        try {
            $idBrokerClient = self::getIdBrokerClient($state['idBrokerConfig']);
            $mfaOption = $idBrokerClient->mfaCreate($state['employeeId'], 'manager');
            $mfaOption['type'] = 'manager';

            $logger->warning(json_encode([
                'event' => 'Manager rescue code sent',
                'employeeId' => $state['employeeId'],
            ]));
        } catch (\Throwable $t) {
            $logger->error(json_encode([
                'event' => 'Manager rescue code: failed',
                'employeeId' => $state['employeeId'],
                'error' => $t->getCode() . ': ' . $t->getMessage(),
            ]));
        }

        $mfaOptions = $state['mfaOptions'];

        /*
         * Add this option into the list, giving it a key so `mfaOptions` doesn't get multiple entries
         * if the user tries multiple times.
         */
        $mfaOptions['manager'] = $mfaOption;
        $state['mfaOptions'] = $mfaOptions;
        $stateId = SimpleSAML_Auth_State::saveState($state, self::STAGE_SENT_TO_MFA_PROMPT);

        $url = SimpleSAML\Module::getModuleURL('mfa/prompt-for-mfa.php');

        HTTP::redirectTrustedURL($url, ['mfaId' => $mfaOption['id'], 'StateId' => $stateId]);
    }

    /**
     * See if the user has a manager_email.
     *
     * @param array $state
     * @return bool
     */
    public static function hasManagerEmail($state)
    {
        $managerEmail = $state['Attributes']['manager_email'] ?? [''];
        if (empty($managerEmail[0])) {
            return false;
        }
        return true;
    }
}
