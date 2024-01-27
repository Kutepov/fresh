<?php

use yii\db\Migration;

/**
 * Class m210305_133821_clean
 */
class m210305_133821_clean extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->update('sources_urls', [
            'last_scraped_at' => null,
            'last_scraped_article_date' => null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210305_133821_clean cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210305_133821_clean cannot be reverted.\n";

        return false;
    }
    */
}
