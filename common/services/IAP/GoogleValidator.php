<?php namespace common\services\IAP;

use ReceiptValidator\GooglePlay\Validator;

class GoogleValidator extends IAPValidator
{
    private $client;

    public function __construct(\Google\Client $client, $config = [])
    {
        $this->client = $client;
        $this->client->setScopes([\Google_Service_AndroidPublisher::ANDROIDPUBLISHER]);

        parent::__construct($config);
    }

    public function validateSubscription(?string $token, ?string $subscriptionId): bool
    {
        if (!$token || !$subscriptionId) {
            throw new \Exception('Invalid token or subscription ID');
        }

        if (!in_array($subscriptionId, $this->subscriptionsIds, true)) {
            throw new \Exception('Invalid subscription id ' . $subscriptionId);
        }

        $publisher = new \Google_Service_AndroidPublisher($this->client);
        $validator = new Validator($publisher);

        try {
            $response = $validator->setPackageName($this->packageName)
                ->setProductId($subscriptionId)
                ->setPurchaseToken($token)
                ->validateSubscription();
        } catch (\Exception $e) {
            return false;
        }

        return $response->getExpiryTimeMillis() > time() * 1000;
    }
}