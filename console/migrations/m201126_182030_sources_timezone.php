<?php

use yii\db\Migration;

/**
 * Class m201126_182030_sources_timezone
 */
class m201126_182030_sources_timezone extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources', 'timezone', $this->string(48)->after('status'));
        $this->addColumn('sources_urls', 'timezone', $this->string(48)->after('status'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201126_182030_sources_timezone cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201126_182030_sources_timezone cannot be reverted.\n";

        return false;
    }
    */
}
