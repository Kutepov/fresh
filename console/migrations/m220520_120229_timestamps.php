<?php

use yii\db\Migration;

/**
 * Class m220520_120229_timestamps
 */
class m220520_120229_timestamps extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('ad_banners', 'created_at', $this->timestamp()->after('id')->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
        $this->addColumn('ad_banners', 'updated_at', $this->timestamp()->after('id')->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
        $this->addColumn('categories', 'created_at', $this->timestamp()->after('id')->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
        $this->addColumn('categories', 'updated_at', $this->timestamp()->after('id')->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
        $this->addColumn('countries', 'created_at', $this->timestamp()->after('id')->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
        $this->addColumn('countries', 'updated_at', $this->timestamp()->after('id')->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220520_120229_timestamps cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220520_120229_timestamps cannot be reverted.\n";

        return false;
    }
    */
}
