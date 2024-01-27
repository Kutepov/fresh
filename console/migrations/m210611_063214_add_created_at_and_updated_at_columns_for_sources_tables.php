<?php

use yii\db\Migration;

/**
 * Class m210611_063214_add_created_at_and_updated_at_columns_for_sources_tables
 */
class m210611_063214_add_created_at_and_updated_at_columns_for_sources_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%sources}}', 'created_at', $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
        $this->addColumn('{{%sources}}', 'updated_at', $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
        $this->addColumn('{{%sources_urls}}', 'created_at', $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
        $this->addColumn('{{%sources_urls}}', 'updated_at', $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%sources}}', 'created_at');
        $this->dropColumn('{{%sources}}', 'updated_at');
        $this->dropColumn('{{%sources_urls}}', 'created_at');
        $this->dropColumn('{{%sources_urls}}', 'updated_at');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210611_063214_add_created_at_and_updated_at_columns_for_sources_tables cannot be reverted.\n";

        return false;
    }
    */
}
