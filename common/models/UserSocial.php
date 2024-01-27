<?php namespace common\models;

use yii\db\ActiveRecord;

/**
 * Class UserSocial
 *
 * @property string $url
 * @property User $user
 * @property string $source
 * @property string $source_id
 * @property integer $user_id
 */
class UserSocial extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%users_socials}}';
    }

    public function rules()
    {
        return [
            [['user_id'], 'integer'],
            [['source', 'source_id'], 'safe']
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, [
            'id' => 'user_id'
        ]);
    }
}