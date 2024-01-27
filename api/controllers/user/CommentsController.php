<?php namespace api\controllers\user;

use api\controllers\Controller;
use api\models\search\CommentSearch;
use api\responses\ValidationErrorBadResponse;
use common\services\CommentsService;
use yii\base\UserException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\Request;
use yii;

class CommentsController extends Controller
{
    private $service;

    public function __construct($id,
                                $module,
                                CommentsService $service,
                                $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = ArrayHelper::merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    '*' => ['get']
                ]
            ]
        ]);

        $behaviors['authenticator']['optional'] = ['list'];

        return $behaviors;
    }

    public function actionList(Request $request, $userId = null)
    {
        $searchForm = new CommentSearch(CommentSearch::SCENARIO_USER_PROFILE);

        if ($request->get('top')) {
            $searchForm->scenario = CommentSearch::SCENARIO_USER_PROFILE_TOP;
        }
        $searchForm->userId = $userId ?: Yii::$app->user->id;

        try {
            $searchForm->loadAndValidate($request->get());
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($searchForm->hasErrors()) {
            return new ValidationErrorBadResponse($searchForm->getFirstErrors());
        }

        return $this->service->getList($searchForm);
    }
}