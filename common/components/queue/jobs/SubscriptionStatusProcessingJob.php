<?php declare(strict_types=1);

namespace common\components\queue\jobs;

use common\models\App;
use common\services\adapty\AdaptyService;

class SubscriptionStatusProcessingJob extends Job
{
    public $uid;
    public $platform;

    public function execute($queue)
    {
        $adapty = \Yii::$container->get(AdaptyService::class);

        try {
            $subscriptionStatus = $adapty->getSubscriptionStatus($this->uid);

            if ($app = App::findByUUID($this->uid, $this->platform)) {
                $app->updateAttributes([
                    'pro' => $subscriptionStatus->isActive(),
                    'pro_started_at' => $subscriptionStatus->getStartedAt()->toDateTimeString() ?? null,
                    'pro_expires_at' => $subscriptionStatus->getExpiresAt()->toDateTimeString() ?? null
                ]);
            }

        } catch (\Throwable $e) {

        }
    }
}