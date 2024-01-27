<?php

use yii\db\Migration;

/**
 * Class m231025_121333_disable_sources_urls
 */
class m231025_121333_disable_sources_urls extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $sources = \common\models\Source::find()->enabled(false)->all();
        foreach ($sources as $source) {
            foreach ($source->urls as $sourceUrl) {
                $sourceUrl->updateAttributes([
                    'enabled' => false
                ]);
            }
        }

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231025_121333_disable_sources_urls cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231025_121333_disable_sources_urls cannot be reverted.\n";

        return false;
    }
    */
}
