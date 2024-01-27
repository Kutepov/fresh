<?php namespace common\models;

use buzz\models\traits\CommentMixin;
use Carbon\Carbon;
use common\behaviors\CarbonBehavior;
use common\contracts\RateableEntity;
use common\components\helpers\Api;
use common\models\pivot\CommentRating;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\console\Application;
use yii\db\ActiveQuery;

/**
 * This is the model class for table "comments".
 *
 * @property integer $id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property integer $enabled
 * @property boolean $deleted
 * @property boolean $edited
 * @property string $article_id
 * @property integer $user_id
 * @property integer|null $root_comment_id
 * @property integer|null $parent_comment_id
 * @property integer $rating
 * @property integer $answers_count
 * @property string $text
 * @property string $country
 *
 * @property Article $article
 * @property-read User $user
 * @property-read CommentRating[] $currentUserRating
 * @property CommentRating[] $ratings
 * @property-read \common\models\Comment[] $lastAnswers
 * @property-read string $publicationDateLabel
 */
class Comment extends \yii\db\ActiveRecord implements RateableEntity
{
    use CommentMixin;

    public const SCENARIO_USER_PROFILE = 'userProfile';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'comments';
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL
        ];
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at']
            ],
            [
                'class' => TimestampBehavior::class,
                'value' => Carbon::now('UTC')
            ]
        ];
    }

    public function rules()
    {
        return [
            ['enabled', 'default', 'value' => 1],
            [['deleted', 'edited'], 'boolean'],
            ['text', 'filter', 'filter' => 'hEncode'],
            [['answers_count', 'rating'], 'default', 'value' => 0]
        ];
    }

    public function fields(): array
    {
        $fields = [
            'id',
            'date' => function () {
                return $this->created_at->toIso8601String();
            },
            'user',
            'rating',
            'text' => function () {
                if ($this->deleted) {
                    if (Api::version(Api::V_2_10)) {
                        return null;
                    }

                    return \t('Комментарий был удален');
                }

                return htmlspecialchars_decode($this->text, ENT_QUOTES);
            }
        ];

        $fields['rootCommentId'] = 'root_comment_id';
        $fields['parentCommentId'] = 'parent_comment_id';

        if (!(Yii::$app instanceof Application)) {
            $fields['currentRating'] = function () {
                if (isset($this->currentUserRating[0])) {
                    return $this->currentUserRating[0]->rating;
                }
                return null;
            };
        }
        else {
            $fields['currentRating'] = static function () {
                return null;
            };
        }

        if ($this->scenario === self::SCENARIO_USER_PROFILE) {
            $fields['article'] = 'article';
        }
        else {
            $fields['articleId'] = 'article_id';
            if (!$this->root_comment_id) {
                $fields['answersCount'] = function () {
                    return (int)$this->getAnswers()->count();
                };
                $fields['answers'] = function () {
                    $query = $this->getLastAnswers();

                    if (\Yii::$app->user->isGuest || (\Yii::$app->user->identity instanceof App)) {
                        $query = $query->enabled();
                    }
                    else {
                        $query = $query->enabledOrForUser(\Yii::$app->user->identity);
                    }

                    return $query->all();
                };
            }
        }

        if (Api::version(Api::V_2_10)) {
            $fields['deleted'] = function () {
                return (bool)$this->deleted;
            };

            $fields['edited'] = function () {
                return (bool)$this->edited;
            };

            $fields['sharingLink'] = function () {
                $url = $this->article->sharingUrl;
                if (in_array(API_COUNTRY, ['RU', 'BY'])) {
                    return $url;
                }

                if ($this->root_comment_id) {
                    $url .= '#comment-' . $this->root_comment_id . '-' . $this->id;
                }
                else {
                    $url .= '#comment-' . $this->id;
                }

                return $url;
            };
        }

        if (Api::version(Api::V_2_22)) {
            $fields['editable'] = function() {
                return $this->created_at->diffInHours() < 1;
            };
        }

        return $fields;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert || ($this->isAttributeChanged('enabled', false) && $this->enabled)) {
            $this->user->updateCounters([
                'comments_amount' => 1
            ]);
        }
        elseif (!$this->enabled && $this->isAttributeChanged('enabled', false)) {
            $this->user->updateCounters([
                'comments_amount' => -1
            ]);
        }
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, [
            'id' => 'user_id'
        ]);
    }

    public function getAnswers()
    {
        $query = $this
            ->hasMany(self::class, [
                'root_comment_id' => 'id'
            ])
            ->orderByDate()
            ->notDeleted();

        if (!isset(Yii::$app->user) || \Yii::$app->user->isGuest || (\Yii::$app->user->identity instanceof App)) {
            $query = $query->enabled();
        }
        else {
            $query = $query->enabledOrForUser(\Yii::$app->user->identity);
        }

        return $query;
    }

    public function getLastAnswers()
    {
        return $this->getAnswers()->limit(3);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getArticle(): \common\queries\Article
    {
        return $this->hasOne(Article::class, ['id' => 'article_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRatings(): \common\queries\CommentRating
    {
        return $this->hasMany(CommentRating::class, ['comment_id' => 'id']);
    }

    public function getCurrentUserRating(): \common\queries\CommentRating
    {
        return $this
            ->getRatings()
            ->byAppId(defined('API_APP_ID') ? API_APP_ID : null);
    }

    public function getRatingValue(): ?int
    {
        return $this->rating;
    }

    public function getId()
    {
        return $this->id;
    }

    public static function findById($id): ?self
    {
        return self::find()->where(['id' => $id])->one();
    }

    public static function find()
    {
        return (new \common\queries\Comment(get_called_class()));
    }
}
