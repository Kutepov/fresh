<?php

use yii\db\Migration;

/**
 * Class m220225_154130_clear_top_stat
 */
class m220225_154130_clear_top_stat extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $viewsQuery = \common\models\statistics\ArticleView::find()
            ->select('id')
            ->where([
                'AND',
                ['=', 'widget', 'my-feed-top'],
                ['>=', 'created_at', '2022-02-24 14:08:00']
            ])
            ->asArray()
            ->batch(5000);

        foreach ($viewsQuery as $views) {
            $this->delete('articles_views', [
                'id' => \yii\helpers\ArrayHelper::getColumn($views, 'id')
            ]);
        }

        $clicksQuery = \common\models\statistics\ArticleClick::find()
            ->select('id')
            ->where([
                'AND',
                ['=', 'widget', 'my-feed-top'],
                ['>=', 'created_at', '2022-02-24 14:08:00']
            ])
            ->asArray()
            ->batch(5000);

        foreach ($clicksQuery as $clicks) {
            $this->delete('articles_clicks', [
                'id' => \yii\helpers\ArrayHelper::getColumn($clicks, 'id')
            ]);
        }


    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220225_154130_clear_top_stat cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220225_154130_clear_top_stat cannot be reverted.\n";

        return false;
    }
    */
}
