<?php namespace api\controllers;

use common\services\QualitySurveyService;
use yii\web\Request;

class SurveyController extends Controller
{
    private $service;

    public function __construct($id, $module, QualitySurveyService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function actionGood()
    {
        $this->service->good($this->currentApp);

        return true;
    }

    public function actionBad()
    {
        $this->service->bad($this->currentApp);

        return true;
    }

    public function actionFeedback(Request $request)
    {
        $this->service->feedback(
            $this->currentApp,
            $request->post('message')
        );

        return true;
    }
}