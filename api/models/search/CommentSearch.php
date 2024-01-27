<?php namespace api\models\search;

use common\components\validators\DefaultOnError;
use common\components\validators\UUIDValidator;
use common\models\Article;
use common\models\Comment;
use common\models\User;
use yii\base\UserException;

class CommentSearch extends SearchForm
{
    public const SCENARIO_USER_PROFILE = 'userProfile';
    public const SCENARIO_USER_PROFILE_TOP = 'userProfileTop';
    public const SCENARIO_ARTICLE = 'article';
    public const SCENARIO_ARTICLE_TOP = 'articleTop';
    public const SCENARIO_PARENT_COMMENT = 'parentComment';

    public const TOP_SCENARIOS = [
        self::SCENARIO_ARTICLE_TOP,
        self::SCENARIO_USER_PROFILE_TOP,
    ];

    public const ARTICLE_SCENARIOS = [
        self::SCENARIO_ARTICLE,
        self::SCENARIO_ARTICLE_TOP,
        self::SCENARIO_PARENT_COMMENT
    ];

    public const USER_PROFILE_SCENARIOS = [
        self::SCENARIO_USER_PROFILE,
        self::SCENARIO_USER_PROFILE_TOP
    ];

    public $articleId;
    public $parentCommentId;
    public $offset;
    public $limit;
    public $userId;

    public function scenarios(): array
    {
        return [
            self::SCENARIO_USER_PROFILE => ['userId', 'offset', 'limit'],
            self::SCENARIO_USER_PROFILE_TOP => ['userId', 'offset', 'limit'],
            self::SCENARIO_ARTICLE => ['articleId', 'offset', 'limit'],
            self::SCENARIO_ARTICLE_TOP => ['articleId', 'offset', 'limit'],
            self::SCENARIO_PARENT_COMMENT => ['articleId', 'parentCommentId', 'offset', 'limit']
        ];
    }

    public function rules(): array
    {
        return [
            [['articleId', 'parentCommentId', 'userId'], 'required'],
            ['userId', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            ['articleId', UUIDValidator::class],
            ['articleId', 'exist', 'targetClass' => Article::class, 'targetAttribute' => 'id'],
            ['parentCommentId', 'integer'],
            ['parentCommentId', 'exist', 'targetClass' => Comment::class, 'targetAttribute' => 'id'],
            ['offset', 'integer', 'min' => 0],
            ['offset', DefaultOnError::class, 'value' => 0],
            ['limit', 'default', 'value' => 10],
            ['limit', 'integer', 'min' => 1, 'max' => 30],
            ['limit', DefaultOnError::class, 'value' => 1000000]
        ];
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
        if ($this->hasErrors('userId')) {
            throw new UserException(\t('Пользователь не найден'));
        }
    }
}