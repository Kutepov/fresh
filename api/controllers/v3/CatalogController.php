<?php declare(strict_types=1);

namespace api\controllers\v3;

use api\controllers\Controller;
use common\models\Source;
use common\services\CatalogService;
use yii\web\Request;

class CatalogController extends Controller
{
    private $service;

    public function __construct($id, $module, CatalogService $catalog, $config = [])
    {
        $this->service = $catalog;
        parent::__construct($id, $module, $config);
    }

    public function actionCategories(Request $request)
    {
        return $this->service->getCategoriesList(
            $request->get('countryCode'),
            $request->get('language'),
            $request->get('platform')
        );
    }

    public function actionRecommended($type = null, Request $request)
    {
        return $this->service->getRecommendedSources(
            $type,
            $request->get('countryCode'),
            $this->articlesLanguage
        );
    }
}