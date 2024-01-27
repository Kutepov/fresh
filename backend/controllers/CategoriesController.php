<?php namespace backend\controllers;

use himiklab\sortablegrid\SortableGridAction;
use Yii;
use backend\models\Category;
use backend\models\search\CategorySearch;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;

/**
 * CategoriesController implements the CRUD actions for Category model.
 */
class CategoriesController extends BaseController
{
    public function actions()
    {
        return [
            'sort' => [
                'class' => SortableGridAction::class,
                'modelName' => Category::class
            ]
        ];
    }

    public function behaviors()
    {
        $parent = parent::behaviors();
        return ArrayHelper::merge($parent, [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ]);
    }

    /**
     * Lists all Category models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CategorySearch;
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams());

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }


    /**
     * Process Category model.
     * If creation is successful, the browser will be redirected to the 'index' page.
     * @return mixed
     */
    public function actionProcess($id = null)
    {
        if (is_null($id)) {
            $model = new Category;
        } else {
            $model = $this->findModel($id);
            $model->scenario = Category::SCENARIO_UPDATE;
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        } else {
            return $this->render('process', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Category model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * @param $id
     * @return array|\yii\db\ActiveRecord|null
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        if (($model = Category::find()->multilingual()->where(['id' => $id])->one()) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
