<?php
namespace Sil\SspProfileReview\Behat\context;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Mink\Driver\GoutteDriver;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Session;
use PHPUnit\Framework\Assert;
use Sil\PhpEnv\Env;
use Sil\SspProfileReview\Behat\fakes\FakeIdBrokerClient;
use Sil\SspProfileReview\LoginBrowser;

/**
 * Defines application features from the specific context.
 */
class ProfileReviewContext implements Context
{
    protected $nonPwManagerUrl = 'http://sp/module.php/core/authenticate.php?as=profilereview-idp-no-port';
    
    protected $username = null;
    protected $password = null;
    
    /**
     * The browser session, used for interacting with the website.
     *
     * @var Session
     */
    protected $session;
    
    /**
     * The driver for our browser-based testing.
     *
     * @var GoutteDriver
     */
    protected $driver;
    
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
        $this->driver = new GoutteDriver();
        $this->session = new Session($this->driver);
        $this->session->start();
    }
    
    /**
     * Assert that the given page has a form that contains the given text.
     *
     * @param string $text The text (or HTML) to search for.
     * @param DocumentElement $page The page to search in.
     * @return void
     */
    protected function assertFormContains($text, $page)
    {
        $forms = $page->findAll('css', 'form');
        foreach ($forms as $form) {
            if (strpos($form->getHtml(), $text) !== false) {
                return;
            }
        }
        Assert::fail(sprintf(
            "No form found containing %s in this HTML:\n%s",
            var_export($text, true),
            $page->getHtml()
        ));
    }

    /**
     * Get the login button from the given page.
     *
     * @param DocumentElement $page The page.
     * @return NodeElement
     */
    protected function getLoginButton($page)
    {
        $buttons = $page->findAll('css', 'button');
        $loginButton = null;
        foreach ($buttons as $button) {
            $lcButtonText = strtolower($button->getText());
            if (strpos($lcButtonText, 'login') !== false) {
                $loginButton = $button;
                break;
            }
        }
        Assert::assertNotNull($loginButton, 'Failed to find the login button');
        return $loginButton;
    }
    
    /**
     * @When I login
     */
    public function iLogin()
    {
        $this->session->visit($this->nonPwManagerUrl);
        $page = $this->session->getPage();
        try {
            $page->fillField('username', $this->username);
            $page->fillField('password', $this->password);
            $this->submitLoginForm($page);
        } catch (ElementNotFoundException $e) {
            Assert::fail(sprintf(
                "Did not find that element in the page.\nError: %s\nPage content: %s",
                $e->getMessage(),
                $page->getContent()
            ));
        }
    }
    
    /**
     * @Then I should end up at my intended destination
     */
    public function iShouldEndUpAtMyIntendedDestination()
    {
        $page = $this->session->getPage();
        Assert::assertContains('Your attributes', $page->getHtml());
    }
    
    /**
     * Submit the current form, including the secondary page's form (if
     * simpleSAMLphp shows another page because JavaScript isn't supported) by
     * clicking the specified button.
     *
     * @param string $buttonName The value of the desired button's `name`
     *     attribute.
     */
    protected function submitFormByClickingButtonNamed($buttonName)
    {
        $page = $this->session->getPage();
        $button = $page->find('css', sprintf(
            '[name=%s]',
            $buttonName
        ));
        Assert::assertNotNull($button, 'Failed to find button named ' . $buttonName);
        $button->click();
        $this->submitSecondarySspFormIfPresent($page);
    }
    
    /**
     * Submit the login form, including the secondary page's form (if
     * simpleSAMLphp shows another page because JavaScript isn't supported).
     *
     * @param DocumentElement $page The page.
     */
    protected function submitLoginForm($page)
    {
        $loginButton = $this->getLoginButton($page);
        $loginButton->click();
        $this->submitSecondarySspFormIfPresent($page);
    }
    
    /**
     * Submit the secondary page's form (if simpleSAMLphp shows another page
     * because JavaScript isn't supported).
     *
     * @param DocumentElement $page The page.
     */
    protected function submitSecondarySspFormIfPresent($page)
    {
        // SimpleSAMLphp 1.15 markup for secondary page:
        $postLoginSubmitButton = $page->findButton('postLoginSubmitButton');
        if ($postLoginSubmitButton instanceof NodeElement) {
            $postLoginSubmitButton->click();
        } else {
            
            // SimpleSAMLphp 1.14 markup for secondary page:
            $body = $page->find('css', 'body');
            if ($body instanceof NodeElement) {
                $onload = $body->getAttribute('onload');
                if ($onload === "document.getElementsByTagName('input')[0].click();") {
                    $body->pressButton('Submit');
                }
            }
        }
    }
    
    /**
     * @Given I provide credentials that do not need review
     */
    public function iProvideCredentialsThatDoNotNeedReview()
    {
        // See `development/idp-local/config/authsources.php` for options.
        $this->username = 'no_review';
        $this->password = 'a';
    }

    /**
     * @Given I provide credentials that are due for a(n) :category :nagType reminder
     */
    public function iProvideCredentialsThatAreDueForAReminder($category, $nagType)
    {
        // See `development/idp-local/config/authsources.php` for options.
        $this->username = $category . '_' . $nagType;
        $this->password = 'a';
    }

    /**
     * @Given I have logged in (again)
     */
    public function iHaveLoggedIn()
    {
        $this->iLogin();
    }

    protected function pageContainsElementWithText($cssSelector, $text)
    {
        $page = $this->session->getPage();
        $elements = $page->findAll('css', $cssSelector);
        foreach ($elements as $element) {
            if (strpos($element->getText(), $text) !== false) {
                return true;
            }
        }
        return false;
    }
    
    protected function clickLink($text)
    {
        $this->session->getPage()->clickLink($text);
    }

    /**
     * @Then there should be a way to continue to my intended destination
     */
    public function thereShouldBeAWayToContinueToMyIntendedDestination()
    {
        $page = $this->session->getPage();
        $this->assertFormContains('name="continue"', $page);
    }

    /**
     * @When I click the remind-me-later button
     */
    public function iClickTheRemindMeLaterButton()
    {
        $this->submitFormByClickingButtonNamed('continue');
    }

    /**
     * @When I click the set-up-MFA button
     */
    public function iClickTheSetUpMfaButton()
    {
        $this->submitFormByClickingButtonNamed('setUpMfa');
    }

    /**
     * @Then I should end up at the mfa-setup URL
     */
    public function iShouldEndUpAtTheMfaSetupUrl()
    {
        $profileUrl = Env::get('PROFILE_URL_FOR_TESTS');
        Assert::assertNotEmpty($profileUrl, 'No PROFILE_URL_FOR_TESTS provided');
        $currentUrl = $this->session->getCurrentUrl();
        Assert::assertStringStartsWith(
            $profileUrl,
            $currentUrl,
            'Did NOT end up at the MFA-setup URL'
        );
    }

    /**
     * @When I click the set-up-Method button
     */
    public function iClickTheSetUpMethodButton()
    {
        $this->submitFormByClickingButtonNamed('setUpMethod');
    }

    /**
     * @Then I should end up at the method-setup URL
     */
    public function iShouldEndUpAtTheMethodSetupUrl()
    {
        $profileUrl = Env::get('PROFILE_URL_FOR_TESTS');
        Assert::assertNotEmpty($profileUrl, 'No PROFILE_URL_FOR_TESTS provided');
        $currentUrl = $this->session->getCurrentUrl();
        Assert::assertStringStartsWith(
            $profileUrl,
            $currentUrl,
            'Did NOT end up at the Method-setup URL'
        );
    }

    /**
     * @Then I should see a message encouraging me to add a(n) mfa
     */
    public function iShouldSeeAMessageEncouragingMeToAddAnMfa()
    {
        $page = $this->session->getPage();
        Assert::assertContains('increase the security of your account by enabling 2-Step', $page->getHtml());
    }

    /**
     * @Then there should be a way to go add MFA now
     */
    public function thereShouldBeAWayToGoAddMfaNow()
    {
        $page = $this->session->getPage();
        $this->assertFormContains('name="setUpMfa"', $page);
    }

    /**
     * @Then I should see a message encouraging me to add a(n) method
     */
    public function iShouldSeeAMessageEncouragingMeToAddAnMethod()
    {
        $page = $this->session->getPage();
        Assert::assertContains('you can provide alternate email addresses', $page->getHtml());
    }

    /**
     * @Then there should be a way to go add method now
     */
    public function thereShouldBeAWayToGoAddMethodNow()
    {
        $page = $this->session->getPage();
        $this->assertFormContains('name="setUpMethod"', $page);
    }

    /**
     * @Then I should see a message encouraging me to review a(n) mfa
     */
    public function iShouldSeeAMessageEncouragingMeToReviewAnMfa()
    {
        $page = $this->session->getPage();
        Assert::assertContains('time to review your 2-Step', $page->getHtml());
    }

    /**
     * @Then there should be a way to go review MFA now
     */
    public function thereShouldBeAWayToGoReviewMfaNow()
    {
        $page = $this->session->getPage();
        $this->assertFormContains('name="setUpMfa"', $page);
    }

    /**
     * @Then I should see a message encouraging me to review a(n) method
     */
    public function iShouldSeeAMessageEncouragingMeToReviewAMethod()
    {
        $page = $this->session->getPage();
        Assert::assertContains('review your account recovery methods', $page->getHtml());
    }

    /**
     * @Then there should be a way to go review method now
     */
    public function thereShouldBeAWayToGoReviewMethodNow()
    {
        $page = $this->session->getPage();
        $this->assertFormContains('name="setUpMethod"', $page);
    }
}
