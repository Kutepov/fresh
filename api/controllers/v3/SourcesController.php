<?php declare(strict_types=1);

namespace api\controllers\v3;

use api\controllers\Controller;
use api\models\search\CatalogSourceUrlSearch;
use common\services\CatalogSourcesService;
use common\services\SourcesService;
use common\services\SourcesUrlsService;
use yii\web\BadRequestHttpException;
use yii\web\Request;

class SourcesController extends Controller
{
    private $service;
    private SourcesUrlsService $sourcesUrlsService;

    public function __construct($id, $module, CatalogSourcesService $service, SourcesUrlsService $sourcesUrlsService, $config = [])
    {
        $this->service = $service;
        $this->sourcesUrlsService = $sourcesUrlsService;
        parent::__construct($id, $module, $config);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionSearch($query, Request $request)
    {
        try {
            $searchForm = new CatalogSourceUrlSearch();
            return $this->service->find(
                $searchForm->loadAndValidate($request->get()),
                $this->currentApp
            );
        } catch (\Throwable $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    public function actionPreview($id)
    {
        return $this->service->preview(
            $this->sourcesUrlsService->convertUaRuSourcesUrlIdToUkIfNeeded($id)
        );
    }

    public function actionSubscribeBulk(Request $request)
    {
        $this->service->batchSubscribeToSourcesUrls(
            $request->post('sourceUrl', []),
            (bool)$request->post('push', false),
            $this->currentApp
        );
    }

    public function actionUnsubscribeBulk(Request $request)
    {
        $this->service->batchUnsubscribeFromSourcesUrls(
            $request->post('sourceUrl', []),
            $this->currentApp
        );
    }

    public function actionSubscribe($id, Request $request)
    {
        return $this->service->subscribeToSourceUrl(
            $id,
            $this->currentApp,
            $this->country,
            $request->get('folderId')
        );
    }

    public function actionUnsubscribe($id)
    {
        $this->service->unsubscribeFromSourceUrl(
            $id,
            $this->currentApp
        );
    }
}