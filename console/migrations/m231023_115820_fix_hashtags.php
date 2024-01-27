<?php

use yii\db\Migration;

/**
 * Class m231023_115820_fix_hashtags
 */
class m231023_115820_fix_hashtags extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $categories = (new \yii\db\Query())
            ->from('categories_lang')
            ->select('*')
            ->all();

        foreach ($categories as $category) {
            $tag = mb_strtolower(preg_replace('#[^\p{L}\w]#iu', '', $category['title']));

            if (!$tag) {
                continue;
            }
            if (!($existsTag = \common\models\Hashtag::find()->where(['tag' => $tag])->one())) {
                $existsTag = new \common\models\Hashtag([
                    'tag' => $tag
                ]);
                $existsTag->save();
            }

            $sourcesUrls = \common\models\SourceUrl::find()
                ->where(['category_id' => $category['owner_id']])
                ->all();

            foreach ($sourcesUrls as $url) {
                if (!\common\models\pivot\HashtagSourceUrl::find()->where(['source_url_id' => $url->id, 'hashtag_id' => $existsTag->id])->exists()) {
                    $this->insert('hashtags_sources_urls', [
                        'source_url_id' => $url->id,
                        'hashtag_id' => $existsTag->id
                    ]);
                }
            }
        }

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231023_115820_fix_hashtags cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231023_115820_fix_hashtags cannot be reverted.\n";

        return false;
    }
    */
}
