<?php

use yii\db\Migration;

/**
 * Handles adding columns to table `{{%articles}}`.
 */
class m220404_122840_add_description_column_to_articles_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
//        $this->addColumn('{{%articles}}', 'description', $this->text()->after('title'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
//        $this->dropColumn('{{%articles}}', 'description');
    }
}
