<?php

use yii\db\Migration;

/**
 * Class m210305_132631_last_scraped_article_date
 */
class m210305_132631_last_scraped_article_date extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources_urls', 'last_scraped_article_date', $this->timestamp()->after('last_scraped_at'));
        $this->renameColumn('sources_urls', 'last_scraped_at_disabled', 'last_scraped_article_date_disabled');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210305_132631_last_scraped_article_date cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210305_132631_last_scraped_article_date cannot be reverted.\n";

        return false;
    }
    */
}
