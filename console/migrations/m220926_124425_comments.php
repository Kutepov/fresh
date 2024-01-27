<?php

use yii\db\Migration;

/**
 * Class m220926_124425_comments
 */
class m220926_124425_comments extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('comments', 'edited', $this->boolean()->defaultValue(0));
        $this->addColumn('comments', 'deleted', $this->boolean()->defaultValue(0));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220926_124425_comments cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220926_124425_comments cannot be reverted.\n";

        return false;
    }
    */
}
