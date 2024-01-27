<?php namespace console\controllers;

use common\models\App;
use common\models\statistics\ArticleClick;
use common\models\statistics\ArticleView;
use common\services\DbManager;
use yii\db\Connection;
use yii\db\Query;
use yii;

class MigrationController extends Controller
{
    private $dbManager;
    /** @var Connection */
    private $db;

    public function __construct($id, $module, DbManager $dbManager, $config = [])
    {
        $this->dbManager = $dbManager;
        $this->db = $this->dbManager->getUnbufferedConnection();

        parent::__construct($id, $module, $config);
    }

    public function actionIndex($batchSize = 5000): void
    {
        $this->stdout('Clicks start...');
        $this->dates(ArticleClick::tableName(), $batchSize);
        $this->stdout('Clicks end...');

        $this->stdout('Apps start...');
        $this->dates(App::tableName(), $batchSize);
        $this->stdout('Apps end...');

        $this->stdout('Views start...');
        $this->dates(ArticleView::tableName(), $batchSize);
        $this->stdout('Views end...');
    }

    private function dates($tableName, $batchSize): void
    {
        $query = (new Query())
            ->from($tableName)
            ->select('id')
            ->where([
                'OR',
                ['IS', 'date', null],
                ['=', 'date', '0000-00-00']
            ])
            ->orderBy(['id' => SORT_DESC]);
        $count = $query->count();

        foreach ($query->batch($batchSize, $this->db) as $i => $items) {
            $ids = yii\helpers\ArrayHelper::getColumn($items, 'id');
            $this->dbManager->executeWithRetries(function () use ($tableName, &$ids) {
                Yii::$app->db->createCommand()
                    ->update($tableName, [
                        'date' => new yii\db\Expression('DATE(created_at)')
                    ], [
                        'id' => $ids
                    ])->execute();
            }, 10, function (\Throwable $e) {
                $this->stdout('Transaction exception: ' . $e->getCode() . ': ' . $e->getMessage());
            });


            $this->stdout(round(((($i * $batchSize)) / $count) * 100, 2) . '%');
        }
    }
}