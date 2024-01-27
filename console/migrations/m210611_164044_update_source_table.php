<?php

use yii\db\Migration;

/**
 * Class m210611_164044_update_source_table
 */
class m210611_164044_update_source_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute('ALTER TABLE sources MODIFY country char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->execute('SET foreign_key_checks = 0;');
        $this->addForeignKey('fk-sources_country', '{{%sources}}', 'country', '{{%countries}}', 'code', 'cascade', 'cascade');
        $this->addForeignKey('fk-sources_language', '{{%sources}}', 'language', '{{%languages}}', 'code', 'cascade', 'cascade');
        $this->execute('SET foreign_key_checks = 1;');
        $this->alterColumn('{{%sources_urls}}', 'category_id', $this->char(36)->defaultValue(null));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-sources_country', '{{%sources}}');
        $this->dropForeignKey('fk-sources_language', '{{%sources}}');
        $this->execute('ALTER TABLE sources MODIFY country char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $this->alterColumn('{{%sources_urls}}', 'category_id', $this->char(36)->defaultValue(''));
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210611_164044_update_source_table cannot be reverted.\n";

        return false;
    }
    */
}
