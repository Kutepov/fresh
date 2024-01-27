<?php namespace common\services;

use api\models\search\CommentSearch;
use Assert\Assertion;
use common\models\App;
use common\models\Article;
use common\models\Comment;
use common\forms\Comment as CommentForm;
use common\models\pivot\CommentRating;
use yii\base\UserException;

class CommentsService
{
    public function create(CommentForm $form): Comment
    {
        $comment = new Comment();
        $comment->article_id = $form->articleId;

        if ($form->parentCommentId && ($parentComment = Comment::findById($form->parentCommentId))) {
            $comment->parent_comment_id = !$parentComment->root_comment_id ? null : $parentComment->id;
            $comment->root_comment_id = $parentComment->root_comment_id ?: $parentComment->id;
        }

        $comment->country = $form->country;
        $comment->user_id = $form->userId;
        $comment->text = $form->text;
        $comment->enabled = !$form->user->shadow_ban;

        $comment->save();

        $comment->populateRelation('user', $form->getUser());

        Article::updateAllCounters([
            'comments_count' => 1
        ], [
            'id' => $comment->article_id
        ]);

        if ($comment->root_comment_id) {
            Comment::updateAllCounters([
                'answers_count' => 1
            ], [
                'id' => $comment->root_comment_id
            ]);
        }

        return $comment;
    }

    public function update(CommentForm $form): Comment
    {
        $comment = $form->getExistsComment();
        $comment->text = $form->text;
        $comment->edited = true;
        $comment->save();

        return $comment;
    }

    public function delete(CommentForm $form): Comment
    {
        $comment = $form->getExistsComment();
        $comment->deleted = true;
        $comment->save();

        Article::updateAllCounters([
            'comments_count' => -1
        ], [
            'id' => $comment->article_id
        ]);

        $form->getUser()->updateCounters([
            'comments_amount' => -1
        ]);

        if ($comment->root_comment_id) {
            Comment::updateAllCounters([
                'answers_count' => -1
            ], [
                'id' => $comment->root_comment_id
            ]);
        }

        return $comment;
    }

    public function increaseRating($commentId, App $app): int
    {
        return $this->rating($commentId, 1, $app);
    }

    public function decreaseRating($commentId, App $app): int
    {
        return $this->rating($commentId, -1, $app);
    }

    private function rating($commentId, int $value, App $app): int
    {
        Assertion::inArray($value, [1, -1]);

        if ($comment = Comment::findById($commentId)) {
            if ($comment->user_id === $app->user_id) {
                throw new UserException(\t('Нельзя оценивать свой комментарий'));
            }

            $existsRating = $comment
                ->getRatings()
                ->byCommentId($commentId)
                ->byAppId($app->id)
                ->one();

            if ($existsRating) {
                $value = -$existsRating->rating;
                $saved = $existsRating->delete();
            } else {
                $rating = new CommentRating([
                    'comment_id' => $commentId,
                    'app_id' => $app->id,
                    'rating' => $value
                ]);

                $rating->country = $comment->country;
                $saved = $rating->save();
            }

            if ($saved) {
                $comment->updateCounters([
                    'rating' => $value
                ]);

                $comment->user->updateCounters([
                    'rating' => $value
                ]);
            }

            return $comment->rating;
        }

        throw new UserException(\t('Комментарий был удален'));
    }

    /**
     * @param CommentSearch $searchForm
     * @return Comment[]
     */
    public function getList(CommentSearch $searchForm): array
    {
        $query = Comment::find()
            ->with(['user', 'user.appsWithSubscription'])
            ->offset($searchForm->offset)
            ->limit($searchForm->limit);

        if (in_array($searchForm->scenario, CommentSearch::USER_PROFILE_SCENARIOS, true)) {
            $query->innerJoinWith('article');
        }
        else {
            $query->with('article');
        }

        /** Комментарии для новости */
        if (in_array($searchForm->scenario, CommentSearch::ARTICLE_SCENARIOS, true)) {
            $query = $query
                ->byArticleId($searchForm->articleId)
                ->byRootCommentId($searchForm->parentCommentId);

            if (!$searchForm->parentCommentId) {
                $query = $query->rootNotDeleted();
            } else {
                $query = $query->notDeleted();
            }

            if (\Yii::$app->user->isGuest || (\Yii::$app->user->identity instanceof App)) {
                $query = $query->enabled();
            } else {
                $query = $query->enabledOrForUser(\Yii::$app->user->identity);
            }
        } /** Список комментариев пользователя */
        else {
            $query = $query
                ->byUserId($searchForm->userId);
        }

        /** Лучшие комментарии */
        if (in_array($searchForm->scenario, CommentSearch::TOP_SCENARIOS, true)) {
            $query->orderByRating();
        } else {
            /** По дате добавления, для комментов в профиле - по убыванию */
            $query->orderByDate(
                in_array($searchForm->scenario, CommentSearch::ARTICLE_SCENARIOS) ? SORT_ASC : SORT_DESC
            );
        }

        if (in_array($searchForm->scenario, CommentSearch::USER_PROFILE_SCENARIOS)) {
            $query = $query->notDeleted();
        }

        $comments = $query->all();

        if (in_array($searchForm->scenario, CommentSearch::USER_PROFILE_SCENARIOS, true)) {
            foreach ($comments as $k => $comment) {
                if ($comment->article) {
                    $comment->scenario = Comment::SCENARIO_USER_PROFILE;
                    $comment->article->scenario = Article::SCENARIO_USER_PROFILE;
                } else {
                    unset ($comments[$k]);
                }
            }
        }

        return array_values($comments);
    }
}