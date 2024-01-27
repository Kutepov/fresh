<?php namespace common\forms;

use common\components\validators\UUIDValidator;
use common\models\Article;
use common\models\User;
use Yii;
use yii\base\UserException;

/**
 * @property-read null|\common\models\User $user
 */
class Comment extends \yii\base\Model
{
    public const SCENARIO_CREATE = 'create';
    public const SCENARIO_CREATE_ANSWER = 'createAnswer';
    public const SCENARIO_UPDATE = 'update';
    public const SCENARIO_DELETE = 'delete';

    public $id;
    public $articleId;
    public $parentCommentId;
    public $text;
    public $userId;

    public $country;

    /** @var User|null */
    private $_user;
    /** @var \common\models\Comment */
    private $_existsComment;

    public function scenarios(): array
    {
        return [
            self::SCENARIO_CREATE => ['text', 'articleId', 'userId'],
            self::SCENARIO_CREATE_ANSWER => ['text', 'articleId', 'parentCommentId', 'userId'],
            self::SCENARIO_UPDATE => ['id', 'text', 'userId'],
            self::SCENARIO_DELETE => ['id', 'userId']
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['articleId', UUIDValidator::class],
            [['articleId'], 'exist', 'skipOnError' => true, 'targetClass' => Article::class, 'targetAttribute' => ['articleId' => 'id'], 'message' => \t('Новость не найдена')],
            [['articleId', 'parentCommentId', 'id'], 'required'],
            ['text', 'trim'],
            ['text', 'required', 'message' => \t('Комментарий не может быть пустым')],
            ['text', 'string', 'min' => 1, 'max' => 5000, 'tooShort' => \t('Комментарий слишком короткий'), 'tooLong' => \t('Комментарий слишком длинный')],
            ['id', 'required'],
            ['userId', 'validateUser'],
            ['parentCommentId', 'validateParentCommentId'],
            ['id', 'validateExistsCommentId'],
            ['text', 'validateUniqueComment']
        ];
    }

    public function validateUniqueComment()
    {
        if (($prevComment = \common\models\Comment::find()->where([
                'user_id' => $this->userId,
                'article_id' => $this->articleId,
                'parent_comment_id' => $this->parentCommentId
            ])->orderBy(['id' => SORT_DESC])->one()) && $prevComment->text === $this->text) {
            throw new UserException(\t('Такой комментарий был добавлен ранее'));
        }
    }

    public function afterValidate()
    {
        parent::afterValidate();
        if ($this->hasErrors('articleId')) {
            throw new UserException(\t('Новость не найдена или была удалена'));
        }

        if ($this->hasErrors('parentCommentId')) {
            throw new UserException(\t('Комментарий не найден или был удален'));
        }
    }

    public function validateExistsCommentId($attribute, $params): void
    {
        if ($comment = $this->getExistsComment()) {
            if ($comment->user_id != $this->userId || ($this->scenario === self::SCENARIO_UPDATE && $comment->deleted)) {
                throw new UserException(\t('Комментарий не найден или был удален'));
            }
        }
        else {
            throw new UserException(\t('Комментарий не найден или был удален'));
        }
    }

    public function validateParentCommentId($attribute, $params): void
    {
        if (!\common\models\Comment::findById($this->parentCommentId)) {
            throw new UserException(\t('Комментарий не найден или был удален'));
        }
    }

    public function validateUser($attribute, $params): void
    {
        if ($user = $this->getUser()) {
            if ($user->status === User::STATUS_BANNED || $user->apps[0]->banned) {
                throw new UserException(\t('Доступ закрыт'));
            }
            if ($user->status !== User::STATUS_ACTIVE) {
                throw new UserException(\t('Аккаунт не подтвержден'));
            }
        }
        else {
            throw new UserException(\t('Пользователь не найден'));
        }
    }

    public function getExistsComment(): ?\common\models\Comment
    {
        if (is_null($this->_existsComment)) {
            $this->_existsComment = \common\models\Comment::find()
                ->where([
                    'id' => $this->id,
                    'user_id' => $this->userId
                ])
                ->one();
        }

        return $this->_existsComment;
    }

    public function getUser(): ?User
    {
        if (is_null($this->_user)) {
            $this->_user = User::findById($this->userId);
        }

        return $this->_user;
    }

    /**
     * @throws \yii\base\UserException
     */
    public function validate($attributeNames = null, $clearErrors = true)
    {
        if (Yii::$app->mutex->acquire('comment-' . $this->userId)) {
            $result = parent::validate($attributeNames, $clearErrors);
            Yii::$app->mutex->release('comment-' . $this->userId);
            return $result;
        }

        throw new UserException();
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'enabled' => 'Enabled',
            'article_id' => 'Article ID',
            'user_id' => 'User ID',
            'parent_comment_id' => 'Parent Comment ID',
            'rating' => 'Rating',
            'answers_count' => 'Answers Count',
            'text' => 'Text',
        ];
    }

    public function formName(): string
    {
        return '';
    }
}