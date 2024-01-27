<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Class m210531_130416_change_sources_images_path
 */
class m210531_130416_change_sources_images_path extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $sources = (new Query())->select(['image', 'id'])->from('{{%sources}}')->where(['not', ['image' => null]])->all();

        foreach ($sources as $source) {
            $new_source = preg_replace('#^ua/#', 'source/', $source['image']);
            Yii::$app->db->createCommand()->update('{{%sources}}', ['image' => $new_source], ['id' => $source['id']])->execute();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210531_130416_change_sources_images_path cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210531_130416_change_sources_images_path cannot be reverted.\n";

        return false;
    }
    */
}
