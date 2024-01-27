<?php

use yii\db\Migration;

/**
 * Class m210630_112917_calendar_table
 */
class m210630_112917_calendar_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('calendar', [
            'date' => $this->date()
        ]);
        $this->createIndex('idx-calendar-date', 'calendar', 'date');
        $dates = \Carbon\CarbonPeriod::create('2021-01-01', '2030-12-31');
        foreach ($dates as $date) {
            $this->insert('calendar', [
                'date' => $date->format('Y-m-d')
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210630_112917_calendar_table cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210630_112917_calendar_table cannot be reverted.\n";

        return false;
    }
    */
}
