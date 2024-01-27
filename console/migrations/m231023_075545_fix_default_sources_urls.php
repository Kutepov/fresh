<?php

use yii\db\Migration;

/**
 * Class m231023_075545_fix_default_sources_urls
 */
class m231023_075545_fix_default_sources_urls extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sources = \common\models\Source::find()->all();
        foreach ($sources as $source) {
            foreach ($source->urls as $url) {
                if ($url->default != $source->default) {
                    $url->updateAttributes(['default' => $source->default]);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231023_075545_fix_default_sources_urls cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231023_075545_fix_default_sources_urls cannot be reverted.\n";

        return false;
    }
    */
}
