<?php

use yii\db\Migration;

/**
 * Class m231023_182055_move_video_sources_to_politics_category
 */
class m231023_182055_move_video_sources_to_politics_category extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $videoCategoryId = '9376a27c-23ab-11ed-8ab9-b5ff4931cad0';
        $politicsCategoryId = 'e35e1c53-816c-4187-8c7d-5238218c1fbb';

        $videoFolderId = '0542fa7a-cca5-11ed-9498-0242c0a8a023';
        $politicsFolderId = '0560b178-cca5-11ed-8e9a-0242c0a8a023';

        $this->update('sources_urls', [
            'category_id' => $politicsCategoryId,
            'folder_id' => $politicsFolderId
        ], [
            'category_id' => $videoCategoryId
        ]);

        $this->delete('categories_countries', [
            'category_id' => $videoCategoryId
        ]);

        $this->delete('folders_countries', [
            'folder_id' => $videoFolderId
        ]);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231023_182055_move_video_sources_to_politics_category cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231023_182055_move_video_sources_to_politics_category cannot be reverted.\n";

        return false;
    }
    */
}
