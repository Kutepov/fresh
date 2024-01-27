<?php

use yii\db\Migration;

/**
 * Class m230324_125041_hash_tags
 */
class m230324_125041_hash_tags extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('hashtags', [
            'id' => $this->primaryKey(),
            'tag' => $this->string(128)->unique()
        ]);

        $this->createTable('hashtags_sources_urls', [
            'source_url_id' => $this->integer()->notNull(),
            'hashtag_id' => $this->integer()
        ]);
        $this->addForeignKey('fk-hashtag-source_url', 'hashtags_sources_urls', 'hashtag_id', 'hashtags', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('hashtags_sources_urls');
        $this->dropTable('hashtags');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230324_125041_hash_tags cannot be reverted.\n";

        return false;
    }
    */
}
