<?php

use yii\db\Migration;

/**
 * Class m231019_220533_migrate_youtube_to_youtube_preview
 */
class m231019_220533_migrate_youtube_to_youtube_preview extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->update('sources', [
            'type' => 'youtube-preview'
        ], [
            'type' => 'youtube'
        ]);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231019_220533_migrate_youtube_to_youtube_preview cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231019_220533_migrate_youtube_to_youtube_preview cannot be reverted.\n";

        return false;
    }
    */
}
