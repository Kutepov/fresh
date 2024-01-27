<?php namespace buzz\controllers;

use buzz\components\filters\auth\UidCookieAuth;
use common\models\Article;
use Detection\MobileDetect;

class Controller extends \yii\web\Controller
{
    /** @var \common\models\App */
    protected $currentUser;

    public function behaviors(): array
    {
        return [
            'authenticator' => [
                'class' => UidCookieAuth::class
            ]
        ];
    }

    public function beforeAction($action)
    {
        define('API_VERSION', '9999999');
        $parent = parent::beforeAction($action);

        $this->currentUser = \Yii::$app->user->identity;
        return $parent;
    }

    public function render($view, $params = [])
    {
        $params = array_merge($params, [
            'deviceDetector' => new MobileDetect()
        ]);
        return parent::render($view, $params);
    }

}