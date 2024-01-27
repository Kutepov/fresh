<?php namespace api\controllers\statistics;

use api\controllers\Controller;
use common\models\statistics\ArticleClickTemporary;
use common\models\statistics\ArticleViewTemporary;
use common\services\ArticlesService;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;
use yii\web\Request;

/**
 * Class ArticlesController
 * @package api\controllers\statistics
 */
class ArticlesController extends Controller
{
    private $service;

    public function __construct($id, $module, ArticlesService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function actionShared(Request $request)
    {
        if ($articleId = $request->get('articleId')) {
            return $this->service->shared(
                $articleId,
                $this->currentApp
            );
        }

        throw new BadRequestHttpException('Empty article ID');
    }

    /**
     * Сохранение клика по новости
     * @param Request $request
     * @return null
     */
    public function actionClicked(Request $request)
    {
        try {
            $click = new ArticleClickTemporary([
                'article_id' => $request->get('articleId'),
                'app_id' => $this->appsService->findOrCreate(
                    $request->get('devicePlatform'),
                    $request->get('deviceId'),
                    $this->appVersion,
                    $request->get('country'),
                    $this->language,
                    $this->articlesLanguage
                )->id,
                'platform' => $request->get('devicePlatform'),
                'country' => $request->get('country'),
                'widget' => $request->get('widget'),
                'preview_type' => $this->articlesPreviewType
            ]);

            $click->save();
        } catch (\Exception $e) {
            $this->logger->warning($e);
        }

        return null;
    }

    /**
     * Сохранение просмотра новости
     * @param Request $request
     * @return null
     */
    public function actionViewed(Request $request)
    {
        try {
            $view = new ArticleViewTemporary([
                'article_id' => $request->get('articleId'),
                'app_id' => $this->appsService->findOrCreate(
                    $request->get('devicePlatform'),
                    $request->get('deviceId'),
                    $this->appVersion,
                    $request->get('country'),
                    $this->language,
                    $this->articlesLanguage
                )->id,
                'platform' => $request->get('devicePlatform'),
                'country' => $request->get('country'),
                'widget' => $request->get('widget'),
                'preview_type' => $this->articlesPreviewType
            ]);

            $view->save();
        } catch (\Exception $e) {
            $this->logger->warning($e);
        }

        return null;
    }

    /**
     * Сохранение пачки просмотренных новостей
     * @param Request $request
     * @return null
     */
    public function actionBulkViewed(Request $request)
    {
        try {
            if (!($input = $request->bodyParams)) {
                $input = Json::decode($request->rawBody);
            }

            if ($input) {
                foreach ($input['items'] as $item) {
                    $view = new ArticleViewTemporary([
                        'article_id' => $item['id'],
                        'app_id' => $this->appsService->findOrCreate(
                            $item['devicePlatform'] ?: $this->platform,
                            $item['deviceId'] ?: $this->deviceId,
                            $this->appVersion,
                            $item['country'] ?: $this->country,
                            $this->language,
                            $this->articlesLanguage
                        )->id,
                        'platform' => $item['devicePlatform'] ?: $this->platform,
                        'country' => $item['country'] ?: $this->country,
                        'widget' => $item['widget'],
                        'preview_type' => $this->articlesPreviewType
                    ]);

                    $view->save();
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning($e);
        }

        return null;
    }

    /**
     * @return null
     * @deprecated
     */
    public function actionClickedDeprecated()
    {
        return null;
    }

    /**
     * @return null
     * @deprecated
     */
    public function actionViewedDeprecated()
    {
        return null;
    }
}