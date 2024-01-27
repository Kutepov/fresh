<?php

use yii\db\Migration;

/**
 * Class m231023_094232_fix_folder
 */
class m231023_094232_fix_folder extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $service = Yii::$container->get(\common\services\FoldersService::class);
        $aiCategory = \common\models\Category::findOne('2a841296-7025-11ee-a0e7-51e37f009de6');

        $categories = (new \yii\db\Query())
            ->from('categories_lang')
            ->where(['owner_id' => $aiCategory->id])
            ->select('*')
            ->all();

        foreach ($categories as $category) {
            $this->insert('folders_lang', [
                'owner_id' => '2a849f9a-7025-11ee-a9f4-717a8d76b3ca',
                'language' => $category['language'],
                'title' => $category['title']
            ]);
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
        echo "m231023_094232_fix_folder cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231023_094232_fix_folder cannot be reverted.\n";

        return false;
    }
    */
}
