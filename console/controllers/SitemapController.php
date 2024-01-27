<?php declare(strict_types=1);

namespace console\controllers;

use common\services\SitemapService;

class SitemapController extends Controller
{
    private SitemapService $service;

    public function __construct($id, $module, SitemapService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function actionGenerate($skipArticles = false)
    {
        $this->service->generate(!$skipArticles);
    }
}