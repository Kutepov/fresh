<?php

use yii\db\Migration;

/**
 * Class m210318_133427_exceptions_unique_hash
 */
class m210318_133427_exceptions_unique_hash extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources_exceptions', 'hash', $this->string(32)->after('message'));
        $this->createIndex('idx-unique_exception_hash', 'sources_exceptions', ['source_id', 'hash'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('sources_exceptions', 'hash');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210318_133427_exceptions_unique_hash cannot be reverted.\n";

        return false;
    }
    */
}
