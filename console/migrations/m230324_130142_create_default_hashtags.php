<?php

use yii\db\Migration;

/**
 * Class m230324_130142_create_default_hashtags
 */
class m230324_130142_create_default_hashtags extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('categories_lang', 'hashtag_id', $this->integer());
        $this->addForeignKey('fk-categories_lang-hashtag_id', 'categories_lang', 'hashtag_id', 'hashtags', 'id', 'SET NULL', 'CASCADE');

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
    public function down()
    {
        $this->execute('SET FOREIGN_KEY_CHECKS = 0');
        $this->truncateTable('hashtags');
        $this->truncateTable('hashtags_sources_urls');
        $this->dropForeignKey('fk-categories_lang-hashtag_id', 'categories_lang');
        $this->dropColumn('categories_lang', 'hashtag_id');

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230324_130142_create_default_hashtags cannot be reverted.\n";

        return false;
    }
    */
}
