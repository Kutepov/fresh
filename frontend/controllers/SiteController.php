<?php
namespace frontend\controllers;

use frontend\models\forms\Feedback;
use yii\web\Controller;
use Yii;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * {@inheritdoc }
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ]
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        if (Yii::$app->request->isPost) {
            $feedback = new Feedback();
            if ($feedback->load(Yii::$app->request->post()) && $feedback->validate()) {
                $feedback->notify();
            }
            return $this->redirect(Yii::$app->request->referrer ?: Yii::$app->homeUrl);
        }

        return $this->render('index');
    }

    /**
     * @return string
     */
    public function actionPolicy()
    {
        return $this->render('policy');
    }

    public function actionRules()
    {
        return $this->render('rules');
    }

    public function actionContact()
    {
        return $this->render('contact-us');
    }

    public function actionPublishers()
    {
        return $this->render('for-publishers');
    }
}
