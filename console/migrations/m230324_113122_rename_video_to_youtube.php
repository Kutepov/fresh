<?php

use yii\db\Migration;

/**
 * Class m230324_113122_rename_video_to_youtube
 */
class m230324_113122_rename_video_to_youtube extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->update('sources', [
            'type' => \common\models\Source::TYPE_YOUTUBE
        ], [
            'type' => 'video'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m230324_113122_rename_video_to_youtube cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230324_113122_rename_video_to_youtube cannot be reverted.\n";

        return false;
    }
    */
}
