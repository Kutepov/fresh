<?php declare(strict_types=1);

namespace common\components\queue\jobs;

use Carbon\Carbon;
use common\models\Article;
use common\models\SourceUrl;
use common\services\DbManager;
use yii\helpers\ArrayHelper;

class ArticlesChangeCategoryJob extends Job
{
    public $sourceUrlId;
    public $oldCategoryId;

    public function execute($queue)
    {
        $dbManager = \Yii::$container->get(DbManager::class);
        $unbufferedDb = $dbManager->getUnbufferedConnection();

        if ($sourceUrl = SourceUrl::findOne($this->sourceUrlId)) {
            $articlesQuery = \common\models\Article::find()
                ->select('id')
                ->byCategory($this->oldCategoryId)
                ->bySourceUrl($sourceUrl->id)
                ->createdAt(Carbon::now()->subMonths(3), Carbon::now())
                ->batch(100, $unbufferedDb);

            foreach ($articlesQuery as $articles) {
                $ids = ArrayHelper::getColumn($articles, 'id');
                Article::updateAll([
                    'category_id' => $sourceUrl->category_id,
                    'category_name' => $sourceUrl->category->name
                ], [
                    'id'=> $ids
                ]);
            }
        }
    }
}