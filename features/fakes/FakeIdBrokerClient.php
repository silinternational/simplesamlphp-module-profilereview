<?php
namespace Sil\SspProfileReview\Behat\fakes;

use InvalidArgumentException;
use Sil\Idp\IdBroker\Client\exceptions\MfaRateLimitException;

/**
 * FAKE IdP ID Broker API client, used for testing.
 */
class FakeIdBrokerClient
{
    /**
     * Constructor.
     *
     * @param string $baseUri - The base of the API's URL.
     *     Example: 'https://api.example.com/'.
     * @param string $accessToken - Your authorization access (bearer) token.
     * @param array $config - Any other configuration settings.
     */
    public function __construct(
        string $baseUri,
        string $accessToken,
        array $config = []
    ) {
        // No-op
    }
}
