<?php

use yii\db\Migration;

/**
 * Class m231019_105446_add_lost_folder
 */
class m231019_105446_add_lost_folder extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $service = Yii::$container->get(\common\services\FoldersService::class);
        $aiCategory = \common\models\Category::findOne('240ede36-6cc5-11ee-b2a0-c57ee19c7454');
        $service->createFolderForCategory($aiCategory);

        $categories = (new \yii\db\Query())
            ->from('categories_lang')
            ->where(['owner_id' => $aiCategory->id])
            ->select('*')
            ->all();

        foreach ($categories as $category) {
            $tag = convertToHashTag($category['title']);

            if (!$tag) {
                continue;
            }
            if (!($existsTag = \common\models\Hashtag::find()->where(['tag' => $tag])->one())) {
                $existsTag = new \common\models\Hashtag([
                    'tag' => $tag
                ]);
                $existsTag->save();
            }

            $this->update('categories_lang', [
                'hashtag_id' => $existsTag->id
            ], [
                'id' => $category['id']
            ]);

            $sourcesUrls = \common\models\SourceUrl::find()
                ->where(['category_id' => $category['owner_id']])
                ->all();

            foreach ($sourcesUrls as $url) {
                $this->insert('hashtags_sources_urls', [
                    'source_url_id' => $url->id,
                    'hashtag_id' => $existsTag->id
                ]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231019_105446_add_lost_folder cannot be reverted.\n";

        return false;
    }
    */
}
