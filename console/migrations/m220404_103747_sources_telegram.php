<?php

use yii\db\Migration;

/**
 * Class m220404_103747_sources_telegram
 */
class m220404_103747_sources_telegram extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources', 'telegram', $this->boolean()->defaultValue(0));
        $this->addColumn('sources', 'telegram_channel_id', $this->string());

        $this->update('sources', [
            'telegram' => 1,
            'telegram_channel_id' => 'ukraine_fresh_news'
        ], [
            'country' => 'UA',
            'language' => 'uk'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220404_103747_sources_telegram cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220404_103747_sources_telegram cannot be reverted.\n";

        return false;
    }
    */
}
