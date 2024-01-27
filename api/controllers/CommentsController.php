<?php namespace api\controllers;

use api\models\search\CommentSearch;
use api\responses\ValidationErrorBadResponse;
use common\models\Comment;
use common\forms\Comment as CommentForm;
use common\services\CommentsService;
use yii\base\UserException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\Request;
use Yii;

class CommentsController extends Controller
{
    private $service;

    public function __construct($id, $module, CommentsService $commentsService, $config = [])
    {
        $this->service = $commentsService;
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = ArrayHelper::merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'add' => ['post'],
                    'update' => ['post'],
                    'rating-up' => ['post'],
                    'rating-down' => ['post'],
                ],
            ],
        ]);

        $behaviors['authenticator']['optional'] = [
            'list',
            'answers-list',
            'rating-up',
            'rating-down'
        ];

        return $behaviors;
    }

    /**
     * Список комментариев для новости
     * @param Request $request
     * @return array|ValidationErrorBadResponse
     * @throws BadRequestHttpException
     */
    public function actionList(Request $request)
    {
        $searchForm = new CommentSearch(CommentSearch::SCENARIO_ARTICLE);

        if ($request->get('top')) {
            $searchForm->scenario = CommentSearch::SCENARIO_ARTICLE_TOP;
        }

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

    /**
     * Список ответов на комментарий
     * @param Request $request
     * @return array|ValidationErrorBadResponse
     * @throws BadRequestHttpException
     */
    public function actionAnswersList(Request $request)
    {
        $searchForm = new CommentSearch(CommentSearch::SCENARIO_PARENT_COMMENT);
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

    /**
     * Добавление комментария к новости
     * @param Request $request
     * @return ValidationErrorBadResponse|Comment
     * @throws BadRequestHttpException
     */
    public function actionAdd(Request $request)
    {
        $form = new CommentForm([
            'scenario' => $request->get('parentCommentId') ? CommentForm::SCENARIO_CREATE_ANSWER : CommentForm::SCENARIO_CREATE
        ]);

        $form->load($request->get());
        $form->country = $this->country;
        $form->userId = Yii::$app->user->id;
        $form->text = $request->post('text');

        try {
            if (!$form->validate()) {
                return new ValidationErrorBadResponse($form->getFirstErrors());
            }
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->service->create($form);
    }

    /**
     * Редактирование комментария
     * @param \yii\web\Request $request
     * @return \api\responses\ValidationErrorBadResponse|\common\models\Comment
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionEdit(Request $request)
    {
        $form = new CommentForm(['scenario' => CommentForm::SCENARIO_UPDATE]);
        $form->load($request->get());
        $form->userId = Yii::$app->user->id;
        $form->text = $request->post('text');

        try {
            if (!$form->validate()) {
                return new ValidationErrorBadResponse($form->getFirstErrors());
            }
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->service->update($form);
    }

    public function actionDelete(Request $request)
    {
        $form = new CommentForm(['scenario' => CommentForm::SCENARIO_DELETE]);
        $form->load($request->get());
        $form->userId = Yii::$app->user->id;

        try {
            if (!$form->validate()) {
                return new ValidationErrorBadResponse($form->getFirstErrors());
            }
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->service->delete($form);
    }

    /**
     * Повышение рейтинга комментария
     * @param \yii\web\Request $request
     * @return int
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRatingUp(Request $request): int
    {
        try {
            return $this->service->increaseRating(
                $request->get('id'),
                $this->currentApp
            );
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * Понижение рейтинга комментария
     * @param \yii\web\Request $request
     * @return int
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRatingDown(Request $request): int
    {
        try {
            return $this->service->decreaseRating(
                $request->get('id'),
                $this->currentApp
            );
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}