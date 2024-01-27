<?php declare(strict_types=1);

namespace common\services\adapty;

use Assert\Assertion;
use Assert\AssertionFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use yii\helpers\Json;

class AdaptyService
{
    private Client $guzzle;
    public $apiKey;

    public function __construct(Client $guzzle)
    {
        $this->guzzle = $guzzle;
    }

    /**
     * @throws AssertionFailedException
     * @throws GuzzleException
     */
    public function getSubscriptionStatus(string $userId): SubscriptionStatus
    {
        Assertion::uuid($userId);

        $userInfo = Json::decode($this->guzzle->get('https://api.adapty.io/api/v1/sdk/profiles/' . $userId . '/', [
            RequestOptions::HEADERS => [
                'Authorization' => 'Api-Key ' . $this->apiKey
            ],
        ])->getBody()->getContents());

        if (isset($userInfo['data']['paid_access_levels']['premium'])) {
            $premiumInfo = $userInfo['data']['paid_access_levels']['premium'];

            return new SubscriptionStatus(
                $premiumInfo['is_active'],
                $premiumInfo['activated_at'] ?: $premiumInfo['starts_at'],
                $premiumInfo['expires_at']
            );
        }

        return new SubscriptionStatus(false);
    }
}