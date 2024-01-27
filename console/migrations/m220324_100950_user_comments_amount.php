<?php

use yii\db\Migration;

/**
 * Class m220324_100950_user_comments_amount
 */
class m220324_100950_user_comments_amount extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('users', 'comments_amount', $this->integer()->defaultValue(0));
        $users = \common\models\User::find()->innerJoinWith('comments')->all();
        foreach ($users as $user) {
            $user->updateAttributes([
                'comments_amount' => $user->getComments()->count()
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220324_100950_user_comments_amount cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220324_100950_user_comments_amount cannot be reverted.\n";

        return false;
    }
    */
}
