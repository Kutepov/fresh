<?php namespace buzz\controllers;

use api\models\search\CommentSearch;
use api\responses\ValidationErrorBadResponse;
use common\services\CommentsService;
use yii\base\UserException;
use yii\web\BadRequestHttpException;
use yii\web\Request;

class CommentsController extends Controller
{
    private $comments;

    public function __construct($id, $module, CommentsService $comments, $config = [])
    {
        $this->comments = $comments;
        parent::__construct($id, $module, $config);
    }


    public function actionAnswersList(Request $request)
    {
        $searchForm = new CommentSearch(CommentSearch::SCENARIO_PARENT_COMMENT);
        $searchForm->loadAndValidate($request->get());
        if ($searchForm->hasErrors()) {
            return new ValidationErrorBadResponse($searchForm->getFirstErrors());
        }

        $comments = $this->comments->getList($searchForm);

        $response = '';

        foreach ($comments as $comment) {
            $response .= $this->renderPartial('item', ['comment' => $comment]);
        }

        return $response;
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
            return $this->comments->increaseRating(
                $request->get('id'),
                $this->currentUser
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
            return $this->comments->decreaseRating(
                $request->get('id'),
                $this->currentUser
            );
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}