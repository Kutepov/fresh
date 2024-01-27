<?php namespace common\queries;

use common\exceptions\CountryNotFoundException;
use yii\db\ActiveQuery;

/**
 * Class SourceUrl
 * @package common\queries
 *
 * @see \common\models\AdBanner
 */
class AdBanner extends ActiveQuery
{
    public function enabled(): AdBanner
    {
        return $this->andWhere(['enabled' => 1]);
    }

    public function forPlatform(?string $platform): AdBanner
    {
        return $this->andWhere(['platform' => $platform]);
    }

    public function forCountry(?string $country): AdBanner
    {
        return $this->andWhere(['country' => $country]);
    }
}