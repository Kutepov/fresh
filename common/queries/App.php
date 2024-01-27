<?php namespace common\queries;

use common\exceptions\CountryNotFoundException;
use yii\db\ActiveQuery;

/**
 * Class SourceUrl
 * @package common\queries
 *
 * @see \common\models\App
 */
class App extends ActiveQuery
{
    public function byDevice(string $platform, string $deviceId)
    {
        return $this
            ->andWhere([
                'device_id' => $deviceId
            ])
            ->byPlatform($platform);
    }

    public function withEnabledPushes()
    {
        return $this->andWhere([
            'push_notifications' => 1
        ]);
    }

    public function byPlatform(string $platform)
    {
        return $this->andWhere([
            'platform' => $platform
        ]);
    }

    public function iosOnly()
    {
        return $this->byPlatform(\common\models\App::PLATFORM_IOS);
    }

    public function proOnly()
    {
        return $this->andWhere(['apps.pro' => 1]);
    }

    public function androidOnly()
    {
        return $this->byPlatform(\common\models\App::PLATFORM_ANDROID);
    }

}