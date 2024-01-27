<?php namespace common\models\aggregate;

use Yii;

/**
 * This is the model class for table "historical_statistics_users".
 *
 * @property string $date
 * @property string $platform
 * @property string $country
 * @property integer $users_amount
 */
class HistoricalUsersStatistics extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'historical_statistics_users';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['date'], 'safe'],
            [['users_amount'], 'integer'],
            [['platform'], 'string', 'max' => 8],
            [['country'], 'string', 'max' => 2],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'date' => 'Date',
            'platform' => 'Platform',
            'country' => 'Country',
            'users_amount' => 'Users Amount',
        ];
    }
}
