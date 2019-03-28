<?php

use Psr\Log\LoggerInterface;
use Sil\Psr3Adapters\Psr3SamlLogger;
use Sil\SspProfileReview\LoggerFactory;
use SimpleSAML\Utils\HTTP;

/**
 * Filter which prompts the user for profile review.
 *
 * See README.md for sample (and explanation of) expected configuration.
 */
class sspmod_profilereview_Auth_Process_ProfileReview extends SimpleSAML_Auth_ProcessingFilter
{
    const SESSION_TYPE = 'profilereview';
    const STAGE_SENT_TO_NAG = 'profilereview:sent_to_nag';

    private $employeeIdAttr = null;
    private $mfaLearnMoreUrl = null;
    private $profileUrl = null;
    private $skipReviewWhenHeadedToProfile = null;

    /** @var LoggerInterface */
    protected $logger;
    
    /** @var string */
    protected $loggerClass;

    /**
     * Initialize this filter.
     *
     * @param array $config Configuration information about this filter.
     * @param mixed $reserved For future use.
     * @throws Exception
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
        ]);

        $this->mfaLearnMoreUrl = $config['mfaLearnMoreUrl'] ?? null;
        $this->skipReviewWhenHeadedToProfile = $config['skipReviewWhenHeadedToProfile'] ?? true;
    }

    /**
     * @param $config
     * @param $attributes
     * @throws Exception
     */
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
     * Return the saml:RelayState if it begins with "http" or "https". Otherwise
     * return an empty string.
     *
     * @param array $state
     * @returns string
     * @return mixed|string
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
     * Redirect the user to set up profile.
     *
     * @param array $state
     */
    public static function redirectToProfile(&$state)
    {
        $ProfileUrl = $state['ProfileUrl'];
        
        // Tell the profile-setup URL where the user is ultimately trying to go (if known).
        $currentDestination = self::getRelayStateUrl($state);
        if (! empty($currentDestination)) {
            $ProfileUrl = SimpleSAML\Utils\HTTP::addURLParameters(
                $ProfileUrl,
                ['returnTo' => $currentDestination]
            );
        }
        
        $logger = LoggerFactory::getAccordingToState($state);
        $logger->warning(sprintf(
            'profilereview: Sending Employee ID %s to set up profile at %s',
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
        $isHeadedToProfileUrl = self::isHeadedToProfileUrl($state, $this->profileUrl);

        if ($isHeadedToProfileUrl && $this->skipReviewWhenHeadedToProfile) {
            return;
        }

        // Record to the state what logger class to use.
        $state['loggerClass'] = $this->loggerClass;
        
        $state['ProfileUrl'] = $this->profileUrl;

        $profileReview = $this->getAttribute('profile_review', $state);

        /** @noinspection PhpUnusedLocalVariableInspection */
        $mfa = $this->getAttributeAllValues('mfa', $state);

        /** @noinspection PhpUnusedLocalVariableInspection */
        $method = $this->getAttributeAllValues('method', $state);

        if ($profileReview == 'yes') {
            $this->redirectToNag($state, $employeeId, $mfa['options'], $method['options']);
        }

        unset($state['Attributes']['method']);
        unset($state['Attributes']['mfa']);
    }

    /**
     * Redirect user to a specific nag page
     *
     * @param array $state The state data.
     * @param string $employeeId The Employee ID of the user account.
     * @param string $mfaOptions A list of the mfa options.
     * @param string $methodOptions A list of the method options.
     */
    protected function redirectToNag(&$state, $employeeId, $mfaOptions, $methodOptions)
    {
        assert('is_array($state)');

        $this->logger->warning(sprintf(
            'profilereview: Redirecting Employee ID %s to profile review.',
            var_export($employeeId, true)
        ));

        /* Save state and redirect. */
        $state['employeeId'] = $employeeId;
        $state['mfaLearnMoreUrl'] = $this->mfaLearnMoreUrl;
        $state['ProfileUrl'] = $this->profileUrl;
        $state['mfaOptions'] = $mfaOptions;
        $state['methodOptions'] = $methodOptions;

        $stateId = SimpleSAML_Auth_State::saveState($state, self::STAGE_SENT_TO_NAG);
        $url = SimpleSAML\Module::getModuleURL(sprintf('profilereview/review.php'));

        HTTP::redirectTrustedURL($url, array('StateId' => $stateId));
    }
}
