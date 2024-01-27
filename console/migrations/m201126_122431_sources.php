<?php

use yii\db\Migration;

/**
 * Class m201126_122431_sources
 */
class m201126_122431_sources extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources', 'enabled', $this->boolean()->after('id')->defaultValue(1));
        $this->addColumn('sources', 'ios_enabled', $this->boolean()->after('enabled')->defaultValue(1));
        $this->addColumn('sources', 'android_enabled', $this->boolean()->after('ios_enabled')->defaultValue(1));

        $this->addColumn('sources_urls', 'enabled', $this->boolean()->after('id')->defaultValue(1));
        $this->addColumn('sources_urls', 'ios_enabled', $this->boolean()->after('enabled')->defaultValue(1));
        $this->addColumn('sources_urls', 'android_enabled', $this->boolean()->after('ios_enabled')->defaultValue(1));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('sources', 'enabled');
        $this->dropColumn('sources', 'ios_enabled');
        $this->dropColumn('sources', 'android_enabled');
        $this->dropColumn('sources_urls', 'enabled');
        $this->dropColumn('sources_urls', 'ios_enabled');
        $this->dropColumn('sources_urls', 'android_enabled');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201126_122431_sources cannot be reverted.\n";

        return false;
    }
    */
}
