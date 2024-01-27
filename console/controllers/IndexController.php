<?php namespace console\controllers;

use Carbon\Carbon;
use common\models\Country;
use common\services\ArticlesIndexer;
use common\services\DbManager;

class IndexController extends Controller
{
    private $articlesIndexer;
    private $dbManager;

    public function __construct($id, $module, ArticlesIndexer $articlesIndexer, DbManager $dbManager, $config = [])
    {
        $this->articlesIndexer = $articlesIndexer;
        $this->dbManager = $dbManager;
        parent::__construct($id, $module, $config);
    }

    public function actionAllArticles($days = 1)
    {
        $startDate = Carbon::now()->startOfDay();
        $articlesQuery = \common\models\Article::find()
            ->andWhere(['IS', 'same_article_id', null])
            ->createdAt($startDate->subDays($days - 1), Carbon::now()->endOfDay())
            ->orderBy(['created_at' => SORT_DESC])
            ->batch(3000, $this->dbManager->getUnbufferedConnection());

        foreach ($articlesQuery as $k => $articles) {
            $this->articlesIndexer->batchAdd($articles);
            echo (($k + 1) * 3000) . "\r";
        }
    }

    public function actionArticle($id)
    {
        if ($article = \common\models\Article::findOne($id)) {
            $this->articlesIndexer->add($article);
        }
    }

    public function actionCreate()
    {
        $countries = Country::find()->all();
        foreach ($countries as $country) {
            $locales = [];

            if ($country->articlesLanguages) {
                foreach ($country->articlesLanguages as $language) {
                    $locales[] = $language->locale;
                }
            }
            else {
                $locales[] = $country->locale;
            }

            foreach ($locales as $locale) {
                if (!$this->articlesIndexer->isIndexExists($locale)) {
                    $this->articlesIndexer->createIndex($locale);
                }
            }
        }
    }

    public function actionDelete()
    {
        $this->articlesIndexer->deleteIndex('*');
    }
}