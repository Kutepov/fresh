<?php namespace buzz\controllers;

use common\services\users\UsersService;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class UsersController extends Controller
{
    private $service;

    public function __construct($id, $module, UsersService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    /**
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionAvatar($id)
    {
        if ($user = $this->service->getProfile($id)) {
            \Yii::$app->response->format = Response::FORMAT_RAW;
            \Yii::$app->response->headers->set('Content-Type', 'image/jpeg');
            return $user->generateAvatar();
        }

        throw new NotFoundHttpException();
    }
}