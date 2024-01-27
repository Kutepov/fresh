<?php namespace backend\controllers;

use kartik\widgets\ActiveForm;
use Yii;
use backend\models\Source;
use backend\models\search\SourceSearch;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * SourcesController implements the CRUD actions for Source model.
 */
class SourcesController extends BaseController
{
    /**
     * {@inheritdoc}
     */
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
     * Lists all Source models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SourceSearch;
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams());

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }


    /**
     * Process Source model.
     * If creation is successful, the browser will be redirected to the 'index' page.
     * @return mixed
     */
    public function actionProcess($id = null)
    {
        if (is_null($id)) {
            $model = new Source;
            $model->setDefaults();
        } else {
            $model = $this->findModel($id);
        }

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            if ($model->group_id) {
                $model->scenario = Source::SCENARIO_GROUPED;
            }
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        } else {
            return $this->render('process', [
                'model' => $model,
            ]);
        }
    }

    public function actionGetAvailableLanguages($id = '')
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $withSelf = false;
        $model = Source::find()
            ->where(['id' => Yii::$app->request->post('depdrop_parents')[0]])
            ->one();
        if (is_null($model)) {
            $withSelf = true;
            $model = $id ? Source::findOne($id) : new Source();
        }

        $languages = $model->getAvailableLanguagesForDropdown($withSelf);

        $result = [];
        foreach ($languages as $code => $language) {
            $result[] = [
                'id' => $code,
                'name' => $language
            ];
        }

        return ['output' => $result];
    }

    /**
     * Deletes an existing Source model.
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
     * Finds the Source model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Source the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Source::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
