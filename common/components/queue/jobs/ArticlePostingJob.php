<?php namespace common\components\queue\jobs;

use common\contracts\Poster;
use common\models\Article;

class ArticlePostingJob extends Job
{
    public $articleId;

    public function execute($queue)
    {
        $poster = \Yii::$container->get(Poster::class);

        if ($article = Article::findOne($this->articleId)) {
            $poster->postArticle($article);
        }
    }
}