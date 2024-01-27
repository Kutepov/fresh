<?php namespace common\services\IAP;

use yii\base\BaseObject;

abstract class IAPValidator extends BaseObject
{
    protected $subscriptionsIds = [];
    protected $packageName;

    abstract public function validateSubscription(string $token, string $subscriptionId): bool;

    /**
     * @param mixed $packageName
     */
    protected function setPackageName(string $packageName): void
    {
        $this->packageName = $packageName;
    }

    /**
     * @param array $subscriptionsIds
     */
    protected function setSubscriptionsIds(array $subscriptionsIds): void
    {
        $this->subscriptionsIds = $subscriptionsIds;
    }
}