<?php namespace common\services;

use common\models\App;
use common\models\AppSubscription;

class AppsSubscriptionsService
{
    private $appsService;

    public function __construct(AppsService $appsService)
    {
        $this->appsService = $appsService;
    }

    public function findSubscription(string $token, ?App $app = null): ?AppSubscription
    {
        $query = AppSubscription::find();
        if (!is_null($app)) {
            $query->andWhere([
                'app_id' => $app->id
            ]);
        }
        $query->andWhere(['token' => $token]);

        return $query->one();
    }

    public function findOrCreateAndValidate()
    {
    }
}