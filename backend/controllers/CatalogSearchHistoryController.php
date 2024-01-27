<?php declare(strict_types=1);

namespace backend\controllers;

use backend\models\search\CatalogSearchHistorySearch;
use yii\web\Request;

class CatalogSearchHistoryController extends BaseController
{
    public function actionIndex(Request $request): string
    {
        $searchModel = new CatalogSearchHistorySearch();
        $dataProvider = $searchModel->search($request->get());

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel
        ]);
    }
}