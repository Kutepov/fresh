<?php

use yii\db\Migration;

/**
 * Class m220426_093644_settings
 */
class m220426_093644_settings extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $minClicksCount = \Yii::$app->settings->get('SettingsPushNotificationsForm', 'minClicksCount');
        $minCtr = \Yii::$app->settings->get('SettingsPushNotificationsForm', 'minCtr');
        $newArticleTimeLimit = \Yii::$app->settings->get('SettingsPushNotificationsForm', 'newArticleTimeLimit');
        $periodBetweenPushes = \Yii::$app->settings->get('SettingsPushNotificationsForm', 'periodBetweenPushes');

        Yii::$app->settings->set('pushes-UA', 'minClicksCount', $minClicksCount);
        Yii::$app->settings->set('pushes-UA', 'minCtr', $minCtr);
        Yii::$app->settings->set('pushes-UA', 'newArticleTimeLimit', $newArticleTimeLimit);
        Yii::$app->settings->set('pushes-UA', 'periodBetweenPushes', $periodBetweenPushes);
        Yii::$app->settings->set('pushes-UA', 'enabled', true);

        $this->update('sources', [
            'push_notifications' => 1
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220426_093644_settings cannot be reverted.\n";

        return false;
    }
    */
}
