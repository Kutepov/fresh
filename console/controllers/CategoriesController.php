<?php namespace console\controllers;

use common\services\CategoriesService;

class CategoriesController extends Controller
{
    private CategoriesService $service;

    public function __construct($id, $module, CategoriesService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }


    public function actionCheckArticlesExists()
    {
        $this->service->calcArticlesInCategories();
    }
}