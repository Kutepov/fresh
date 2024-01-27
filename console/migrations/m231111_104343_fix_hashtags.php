<?php

use yii\db\Migration;

/**
 * Class m231111_104343_fix_hashtags
 */
class m231111_104343_fix_hashtags extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $sourcesUrls = \common\models\SourceUrl::find()->all();
        foreach ($sourcesUrls as $sourceUrl) {
            $languages = \common\models\pivot\CategoryLang::find()->where(['owner_id' => $sourceUrl->category_id])->all();

            foreach ($languages as $language) {
                if ($language->hashtag_id) {
                    if (!\common\models\pivot\HashtagSourceUrl::find()->where(['source_url_id' => $sourceUrl->id, 'hashtag_id' => $language->hashtag_id])->exists()) {
                        $this->insert('hashtags_sources_urls', [
                            'source_url_id' => $sourceUrl->id,
                            'hashtag_id' => $language->hashtag_id
                        ]);
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231111_104343_fix_hashtags cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231111_104343_fix_hashtags cannot be reverted.\n";

        return false;
    }
    */
}
