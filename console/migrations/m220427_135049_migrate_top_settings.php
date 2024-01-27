<?php

use yii\db\Migration;

/**
 * Class m220427_135049_migrate_top_settings
 */
class m220427_135049_migrate_top_settings extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $service = Yii::$container->get(\common\services\MultilingualService::class);
        foreach ($service->getAvailableCountriesForDropDownList() as $code => $name) {
            Yii::$app->settings->set('top-' . $code, 'topCtrUpdateForComment', Yii::$app->settings->get('SettingsForm', 'topCtrUpdateForComment'));
            Yii::$app->settings->set('top-' . $code, 'topCtrUpdateForRating', Yii::$app->settings->get('SettingsForm', 'topCtrUpdateForRating'));
            Yii::$app->settings->set('top-' . $code, 'ctrPeriod', Yii::$app->settings->get('SettingsForm', 'ctrPeriod'));
            Yii::$app->settings->set('top-' . $code, 'acceleratedNewsPeriod', Yii::$app->settings->get('SettingsForm', 'acceleratedNewsPeriod'));
            Yii::$app->settings->set('top-' . $code, 'ctrDecreaseStartHour', Yii::$app->settings->get('SettingsForm', 'ctrDecreaseStartHour'));
            Yii::$app->settings->set('top-' . $code, 'ctrDecreasePercent', Yii::$app->settings->get('SettingsForm', 'ctrDecreasePercent'));
            Yii::$app->settings->set('top-' . $code, 'maxTopPlace', Yii::$app->settings->get('SettingsForm', 'maxTopPlace'));
            Yii::$app->settings->set('top-' . $code, 'minTopCtr', Yii::$app->settings->get('SettingsForm', 'minTopCtr'));
            Yii::$app->settings->set('top-' . $code, 'minClicksThreshold', Yii::$app->settings->get('SettingsForm', 'minClicksThreshold'));
            Yii::$app->settings->set('top-' . $code, 'newArticleTopTimeLimit', Yii::$app->settings->get('SettingsForm', 'newArticleTopTimeLimit'));
            Yii::$app->settings->set('top-' . $code, 'ctrDecreaseYesterdayPercent', Yii::$app->settings->get('SettingsForm', 'ctrDecreaseYesterdayPercent'));
            Yii::$app->settings->set('top-' . $code, 'topCalculationPeriod', Yii::$app->settings->get('SettingsForm', 'topCalculationPeriod'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220427_135049_migrate_top_settings cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220427_135049_migrate_top_settings cannot be reverted.\n";

        return false;
    }
    */
}
