<?php

use yii\db\Migration;

/**
 * Class m221027_101544_app_survey
 */
class m221027_101544_app_survey extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('countries', 'quality_survey', $this->boolean()->defaultValue(0));
        $this->addColumn('countries', 'quality_survey_good', $this->integer()->defaultValue(0));
        $this->addColumn('countries', 'quality_survey_bad', $this->integer()->defaultValue(0));
        $this->update('countries', [
            'quality_survey' => 1
        ], [
            'code' => 'UA'
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
        echo "m221027_101544_app_survey cannot be reverted.\n";

        return false;
    }
    */
}
