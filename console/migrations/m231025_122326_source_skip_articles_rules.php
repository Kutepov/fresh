<?php

use yii\db\Migration;

/**
 * Class m231025_122326_source_skip_articles_rules
 */
class m231025_122326_source_skip_articles_rules extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources_urls', 'url_skip_regexp', $this->string(320));
        $this->update('sources_urls', [
            'url_skip_regexp' => '^https://www\.nytimes.com/es'
        ], [
            'id' => [
                2872,
                2861
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231025_122326_source_skip_articles_rules cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231025_122326_source_skip_articles_rules cannot be reverted.\n";

        return false;
    }
    */
}
