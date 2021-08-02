<?php

namespace SimpleSAML\Module\profilereview\Auth\Process;

use Psr\Log\LoggerInterface;
use Sil\Psr3Adapters\Psr3SamlLogger;
use Sil\SspProfileReview\LoggerFactory;
use SimpleSAML\Auth\ProcessingFilter;
use SimpleSAML\Auth\State;
use SimpleSAML\Module;
use SimpleSAML\Session;
use SimpleSAML\Utils\HTTP;

/**
 * Filter which prompts the user for profile review.
 *
 * See README.md for sample (and explanation of) expected configuration.
 */
class ProfileReview extends ProcessingFilter
{
    const SESSION_TYPE = 'profilereview';
    const STAGE_SENT_TO_NAG = 'profilereview:sent_to_nag';

    const REVIEW_PAGE = 'review.php';
    const MFA_ADD_PAGE = 'nag-for-mfa.php';
    const METHOD_ADD_PAGE = 'nag-for-method.php';

    private $employeeIdAttr = null;
    private $mfaLearnMoreUrl = null;
    private $profileUrl = null;

    /** @var LoggerInterface */
    protected $logger;
    
    /** @var string */
    protected $loggerClass;

    /**
     * Initialize this filter.
     *
     * @param array $config Configuration information about this filter.
     * @param mixed $reserved For future use.
     * @throws \Exception
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
        $this->profileUrl = $config['profileUrl'] ?? null;
    }

    /**
     * @param $config
     * @param $attributes
     * @throws \Exception
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
     * @throws \Exception
     */
    public static function validateConfigValue($attribute, $value, $logger)
    {
        if (empty($value) || !is_string($value)) {
            $exception = new \Exception(sprintf(
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
        $profileUrl = $state['ProfileUrl'];
        // Tell the profile-setup URL where the user is ultimately trying to go (if known).
        $currentDestination = self::getRelayStateUrl($state);
        if (! empty($currentDestination)) {
            $profileUrl = HTTP::addURLParameters(
                $profileUrl,
                ['returnTo' => $currentDestination]
            );
        }

        $logger = LoggerFactory::getAccordingToState($state);
        $logger->warning(json_encode([
            'module' => 'profilereview',
            'event' => 'redirect to profile',
            'employeeId' => $state['employeeId'],
        ]));

        HTTP::redirectTrustedURL($profileUrl);
    }

    /**
     * Apply this AuthProc Filter. It will either return (indicating that it
     * has completed) or it will redirect the user, in which case it will
     * later call `SimpleSAML\Auth\ProcessingChain::resumeProcessing($state)`.
     *
     * @param array &$state The current state.
     */
    public function process(&$state)
    {
        // Get the necessary info from the state data.
        $employeeId = $this->getAttribute($this->employeeIdAttr, $state);
        $isHeadedToProfileUrl = self::isHeadedToProfileUrl($state, $this->profileUrl);

        $mfa = $this->getAttributeAllValues('mfa', $state);
        $method = $this->getAttributeAllValues('method', $state);
        $profileReview = $this->getAttribute('profile_review', $state);

        if (! $isHeadedToProfileUrl) {
            // Record to the state what logger class to use.
            $state['loggerClass'] = $this->loggerClass;

            $state['ProfileUrl'] = $this->profileUrl;

            if (self::needToShow($mfa['add'], self::MFA_ADD_PAGE)) {
                $this->redirectToNag($state, $employeeId, self::MFA_ADD_PAGE);
            }

            if (self::needToShow($method['add'], self::METHOD_ADD_PAGE)) {
                $this->redirectToNag($state, $employeeId, self::METHOD_ADD_PAGE);
            }

            if (self::needToShow($profileReview, self::REVIEW_PAGE))
            {
                $this->redirectToProfileReview($state, $employeeId, $mfa['options'], $method['options']);
            }
        }

        $this->logger->warning(json_encode([
            'module' => 'profilereview',
            'event' => 'no nag/review needed',
            'isHeadedToProfileUrl' => $isHeadedToProfileUrl,
            'profileReview' => $profileReview,
            'mfa.add' => $mfa['add'],
            'method.add' => $method['add'],
            'employeeId' => $employeeId,
        ]));

        unset($state['Attributes']['method']);
        unset($state['Attributes']['mfa']);
        return;
    }

    /**
     * Redirect user to profile review page unless there is nothing to review
     *
     * @param array $state The state data.
     * @param string $employeeId The Employee ID of the user account.
     * @param array $mfaOptions A list of the mfa options.
     * @param array $methodOptions A list of the method options.
     */
    protected function redirectToProfileReview(&$state, $employeeId, $mfaOptions, $methodOptions)
    {
        assert('is_array($state)');

        foreach ($mfaOptions as $key => $mfaOption) {
            if ($mfaOption['type'] === 'manager') {
                unset ($mfaOptions[$key]);
            }
        }

        if (count($mfaOptions) == 0 && count($methodOptions) == 0) {
            return;
        }

        /* Save state and redirect. */
        $state['employeeId'] = $employeeId;
        $state['profileUrl'] = $this->profileUrl;
        $state['mfaOptions'] = $mfaOptions;
        $state['methodOptions'] = $methodOptions;
        $state['template'] = 'review.php';

        $stateId = State::saveState($state, self::STAGE_SENT_TO_NAG);
        $url = Module::getModuleURL('profilereview/nag.php');

        HTTP::redirectTrustedURL($url, array('StateId' => $stateId));
    }

    /**
     * @param array $state
     * @param string $employeeId
     * @param string $template
     */
    protected function redirectToNag(&$state, $employeeId, $template)
    {
        /* Save state and redirect. */
        $state['employeeId'] = $employeeId;
        $state['mfaLearnMoreUrl'] = $this->mfaLearnMoreUrl;
        $state['profileUrl'] = $this->profileUrl;
        $state['template'] = $template;

        $stateId = State::saveState($state, self::STAGE_SENT_TO_NAG);
        $url = Module::getModuleURL('profilereview/nag.php');

        HTTP::redirectTrustedURL($url, array('StateId' => $stateId));
    }

    public static function hasSeenSplashPageRecently(string $page)
    {
        $session = Session::getSessionFromRequest();
        return (bool)$session->getData(
            self::SESSION_TYPE,
            $page
        );
    }

    public static function skipSplashPagesFor($seconds, string $page)
    {
        $session = Session::getSessionFromRequest();
        $session->setData(
            self::SESSION_TYPE,
            $page,
            true,
            $seconds
        );
        $session->save();
    }

    public static function needToShow($flag, $page)
    {
        $oneDay = 24 * 60 * 60;
        if ($flag === 'yes' && ! self::hasSeenSplashPageRecently($page)) {
            self::skipSplashPagesFor($oneDay, $page);
            return true;
        }
        return false;
    }
}
