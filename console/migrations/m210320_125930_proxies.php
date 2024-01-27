<?php

use yii\db\Migration;

/**
 * Class m210320_125930_proxies
 */
class m210320_125930_proxies extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('proxies', 'account', $this->string(32));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210320_125930_proxies cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210320_125930_proxies cannot be reverted.\n";

        return false;
    }
    */
}
