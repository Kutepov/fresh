<?php namespace buzz\widgets;

use api\models\search\TopArticlesSearch;
use common\models\Article;
use common\services\ArticlesService;
use yii\base\Widget;

class TopArticles extends Widget
{
    /** @var ArticlesService */
    private $service;

    public function init()
    {
        $this->service = \Yii::$container->get(ArticlesService::class);
        parent::init();
    }

    public function run()
    {
        $searchForm = new TopArticlesSearch();
        $searchForm->loadAndValidate([
            'limit' => 4,
            'country' => CURRENT_COUNTRY,
            'articlesLanguage' => CURRENT_ARTICLES_LANGUAGE
        ]);

        $articles = $this->service->getTopArticles($searchForm);

        usort($articles, static function (Article $article1, Article $article2) {
            if ($article1->created_at == $article2->created_at) {
                return 0;
            }
            return ($article1->created_at > $article2->created_at) ? -1 : 1;
        });

        return $this->render('top-articles/widget', [
            'articles' => $articles
        ]);
    }
}