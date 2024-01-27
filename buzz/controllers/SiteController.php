<?php namespace buzz\controllers;

use buzz\models\forms\Feedback;
use common\components\helpers\SEO;
use yii\web\Request;
use yii;

class SiteController extends Controller
{
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    public function actionPrivacyPolicy()
    {
        SEO::noIndexNofollow();
        if (Yii::$app->language != 'en_US') {
            throw new yii\web\NotFoundHttpException();
        }
        $this->view->params['languages'] = 'en';
        return $this->render('privacy-policy');
    }

    public function actionFeedback(Request $request)
    {
        if (!Yii::$app->request->isAjax){
            return $this->redirect(['articles/index']);
        }

        if (!in_array(CURRENT_LANGUAGE, ['ru', 'uk'])) {
            \Yii::$app->language = 'en';
        }

        $model = new Feedback();
        if ($request->isPost) {
            if ($model->load($request->post()) && $model->validate() && $model->send()) {
                return $this->redirect(Yii::$app->request->referrer ?: Yii::$app->homeUrl);
            }
        }

        return $this->renderAjax('feedback', [
            'model' => $model
        ]);
    }
}