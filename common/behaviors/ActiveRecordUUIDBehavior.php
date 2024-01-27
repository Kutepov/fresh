<?php namespace common\behaviors;

use Ramsey\Uuid\Uuid;
use yii\base\Behavior;
use yii\db\ActiveRecord;

/**
 * UUID вместо Auto Increment для обратной совместимости с мобильными приложениями
 * @package common\behaviors
 */
class ActiveRecordUUIDBehavior extends Behavior
{
    public $column = 'id';

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeCreate',
        ];
    }

    public function beforeCreate()
    {
        if (empty($this->owner->{$this->column})) {
            $this->owner->{$this->column} = $this->createUUID();
        }
    }

    public function createUUID()
    {
        return Uuid::uuid1()->toString();
    }
}
