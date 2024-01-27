<?php declare(strict_types=1);

namespace api\controllers\v3;

use api\controllers\Controller;
use common\services\FoldersService;
use yii\web\Request;

class FoldersController extends Controller
{
    private $service;

    public function __construct($id, $module, FoldersService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(Request $request)
    {
        return $this->service->getFoldersList(
            $request->get('countryCode'),
            $request->get('language'),
            $this->articlesLanguage,
            $request->get('platform')
        );
    }

    public function actionBackup(Request $request)
    {

    }

    public function actionRestore(Request $request)
    {

    }
}