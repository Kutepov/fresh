<?php namespace console\controllers;

use common\contracts\Poster;

class TelegramController extends Controller
{
    private $service;

    public function __construct($id, $module, Poster $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function actionApproveRequests()
    {
        $this->service->approveRequests();
    }
}