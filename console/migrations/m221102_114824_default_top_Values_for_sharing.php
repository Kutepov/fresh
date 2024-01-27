<?php

use yii\db\Migration;

/**
 * Class m221102_114824_default_top_Values_for_sharing
 */
class m221102_114824_default_top_Values_for_sharing extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $service = Yii::$container->get(\common\services\MultilingualService::class);
        foreach ($service->getAvailableCountriesForDropDownList() as $code => $name) {
            Yii::$app->settings->set('top-' . $code, 'topCtrUpdateForSharing', \backend\models\forms\SettingsForm::get('topCtrUpdateForRating', $code));
        }
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
        echo "m221102_114824_default_top_Values_for_sharing cannot be reverted.\n";

        return false;
    }
    */
}
