<?php

use yii\db\Migration;

/**
 * Class m210320_125456_proxies
 */
class m210320_125456_proxies extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->dropTable('banned_proxies');
        $this->dropTable('banned_proxies_time');
        $this->addColumn('proxies', 'country', $this->string(2)->defaultValue(null));

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210320_125456_proxies cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210320_125456_proxies cannot be reverted.\n";

        return false;
    }
    */
}
