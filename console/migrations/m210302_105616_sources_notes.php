<?php

use yii\db\Migration;

/**
 * Class m210302_105616_sources_notes
 */
class m210302_105616_sources_notes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources', 'note', $this->text());
        $this->addColumn('sources_urls', 'note', $this->text());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210302_105616_sources_notes cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210302_105616_sources_notes cannot be reverted.\n";

        return false;
    }
    */
}
