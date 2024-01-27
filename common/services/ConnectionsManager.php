<?php namespace common\services;

use yii\base\BaseObject;
use yii;

class ConnectionsManager extends BaseObject
{
    public function closeAllConnections()
    {
        Yii::$app->db->close();
        Yii::$app->redis->close();
    }

    public function openAllConnections()
    {
        Yii::$app->db->open();
        Yii::$app->redis->open();
    }

    public function reopenAllConnections()
    {
        $this->closeAllConnections();
        $this->openAllConnections();
    }
}