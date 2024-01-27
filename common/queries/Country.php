<?php namespace common\queries;

use Carbon\Carbon;
use common\exceptions\CountryNotFoundException;
use yii\db\ActiveQuery;

/**
 * Class SourceUrl
 * @package common\queries
 *
 * @see \common\models\Country
 */
class Country extends ActiveQuery
{
    public function notLocked($minutes)
    {
        return $this->andWhere([
            'OR',
            ['=', 'top_locked', 0],
            ['<=', 'top_calculated_at', Carbon::now()->subMinutes($minutes * 2)->toDateTimeString()],
        ]);
    }

    public function topCalculatedMinutesAgo($minutes)
    {
        return $this->andWhere([
            'OR',
            ['<=', 'top_calculated_at', Carbon::now()->subMinutes($minutes)->toDateTimeString()],
            ['IS', 'top_calculated_at', null]
        ]);
    }
}