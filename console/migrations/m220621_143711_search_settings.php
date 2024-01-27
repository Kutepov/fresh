<?php

use yii\db\Migration;

/**
 * Class m220621_143711_search_settings
 */
class m220621_143711_search_settings extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $service = Yii::$container->get(\common\services\MultilingualService::class);
        foreach ($service->getAvailableCountriesForDropDownList() as $code => $name) {
            Yii::$app->settings->set('search-' . $code, 'topQueriesPeriod', 24);
            Yii::$app->settings->set('search-' . $code, 'topQueriesAmount', 5);
            Yii::$app->settings->set('search-' . $code, 'topArticlesAmount', 5);
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
        echo "m220621_143711_search_settings cannot be reverted.\n";

        return false;
    }
    */
}
