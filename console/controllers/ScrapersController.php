<?php namespace console\controllers;

use Carbon\Carbon;
use common\models\SourceUrl;
use common\models\SourceUrlLock;
use yii\helpers\ArrayHelper;

class ScrapersController extends Controller
{
    public function actionUnlockSourcesUrls(): void
    {
        $urls = SourceUrl::find()
            ->where([
                '<=', 'locked_at', Carbon::parse('5 minutes ago')->toDateTimeString()
            ])
            ->all();

        /** @var SourceUrl $url */
        foreach ($urls as $url) {
            $url->unlockForScrapingByCron();
        }
    }

    public function actionUnlockAllSourcesUrls(): void
    {
        $urls = SourceUrl::find()
            ->locked()
            ->all();

        foreach ($urls as $url) {
            $url->unlockForScraping(false);
        }
    }

    public function actionClearLocksLogs(): void
    {
        $logsQuery = SourceUrlLock::find()
            ->asArray()
            ->where(['<=', 'locked_at', Carbon::parse('-2 days')])
            ->select('id')
            ->batch(10000);

        foreach ($logsQuery as $logs) {
            $ids = ArrayHelper::getColumn($logs, 'id');
            SourceUrlLock::deleteAll(['id' => $ids]);
        }
    }
}