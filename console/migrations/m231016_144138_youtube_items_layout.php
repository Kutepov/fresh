<?php

use yii\db\Migration;

/**
 * Class m231016_144138_youtube_items_layout
 */
class m231016_144138_youtube_items_layout extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->update('sources', [
            'type' => \common\models\Source::TYPE_YOUTUBE_PREVIEW
        ], [
            'default' => 0,
            'type' => \common\models\Source::TYPE_YOUTUBE
        ]);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231016_144138_youtube_items_layout cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231016_144138_youtube_items_layout cannot be reverted.\n";

        return false;
    }
    */
}
