<?php

use yii\db\Migration;

/**
 * Class m210315_135911_sources_language_index
 */
class m210315_135911_sources_language_index extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createIndex('idx-source-language', 'sources', ['enabled', 'country', 'language']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210315_135911_sources_language_index cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210315_135911_sources_language_index cannot be reverted.\n";

        return false;
    }
    */
}
