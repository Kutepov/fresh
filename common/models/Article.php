<?php namespace common\models;

use buzz\models\traits\ArticleMixin;
use Carbon\Carbon;
use common\behaviors\ActiveRecordUUIDBehavior;
use common\components\helpers\Api;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use common\components\validators\TimestampValidator;
use common\components\validators\UUIDValidator;
use common\contracts\Logger;
use common\contracts\RateableEntity;
use common\models\aggregate\ArticlesStatistics;
use common\models\pivot\ArticleRating;
use common\services\ArticlesIndexer;
use common\services\QueueManager;
use common\services\RestrictedWordsChecker;
use common\behaviors\CarbonBehavior;
use yii;

/**
 * This is the model class for table "articles".
 *
 * @property string $id
 * @property string $same_article_id
 * @property integer $enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $published_at
 * @property string $source_url_id
 * @property string $source_id
 * @property string $category_id
 * @property string $category_name
 * @property string $title
 * @property string $description
 * @property array $body
 * @property string $url
 * @property string|null $slug
 * @property string $preview_image
 * @property integer $banned_words
 * @property integer $same_articles_amount
 * @property integer $rating
 * @property integer $comments_count
 * @property integer $ratings_count
 * @property integer $shares_count
 *
 * @property-read string|null $previewImageUrl
 * @property-read string $bodyAsString
 * @property-read ArticleRating[] $currentUserRating
 * @property-read array $mediaForTelegram
 *
 * @property Category $category
 * @property Source $source
 * @property SourceUrl $sourceUrl
 * @property-read string $locale
 * @property-read \common\models\ArticleShare[] $shares
 * @property-read int|null $ratingValue
 * @property-read string $sharingUrl
 * @property-read string $metaTitle
 * @property-read Comment[] $comments
 */
class Article extends \yii\db\ActiveRecord implements RateableEntity
{
    use ArticleMixin;

    public const SCENARIO_USER_PROFILE = 'userProfile';
    public const SCENARIO_WIDGET = 'widget';

    public const BODY_PART_CAPTION = 'caption'; // H1 заголовок
    public const BODY_PART_PARAGRAPH = 'paragraph'; // Абзац текста
    public const BODY_PART_TABLE = 'table'; // Таблица - HTML
    public const BODY_PART_RICH_PARAGRAPH = 'rich-paragraph'; // Не используется больше, как я понял
    public const BODY_PART_QUOTE = 'quote'; //Цитата - текст
    public const BODY_PART_UL = 'ul'; // маркированный список - массив строк
    public const BODY_PART_OL = 'ol'; // нумерованный список - массив строк
    public const BODY_PART_VIDEO = 'video'; // URL на видео

    public const BODY_PART_VIDEO_PREVIEW = 'video-preview'; // URL на видео с картинкой-превью
    public const BODY_PART_VIDEO_SOURCE = 'video-source'; // URL на видео из тега <source> внутри тега <video>
    public const BODY_PART_SOUNDCLOUD = 'soundcloud'; //Значение src из iframe soundcloud
    public const BODY_PART_IMAGE = 'image'; // URL на картинку, захешированный через HashImageService
    public const BODY_PART_CAROUSEL = 'carousel'; // Массив с URL-ми на картинки, хешированные через HashImageService
    public const BODY_PART_CHART = 'chart'; // Урл на embeded-график, показывать в вебвью
    public const BODY_PART_INSTAGRAM = 'instagram';
    public const BODY_PART_TWITTER = 'twitter';
    public const BODY_PART_FACEBOOK = 'facebook';
    public const BODY_PART_TELEGRAM = 'telegram';
    public const BODY_PART_MAP = 'map';
    public const BODY_PART_VK = 'vk';

    public const AVAILABLE_BODY_PARTS = [
        self::BODY_PART_CAPTION,
        self::BODY_PART_PARAGRAPH,
        self::BODY_PART_TABLE,
        self::BODY_PART_RICH_PARAGRAPH,
        self::BODY_PART_QUOTE,
        self::BODY_PART_UL,
        self::BODY_PART_OL,
        self::BODY_PART_VIDEO,
        self::BODY_PART_VIDEO_SOURCE,
        self::BODY_PART_SOUNDCLOUD,
        self::BODY_PART_IMAGE,
        self::BODY_PART_INSTAGRAM,
        self::BODY_PART_TWITTER,
        self::BODY_PART_FACEBOOK,
        self::BODY_PART_TELEGRAM,
        self::BODY_PART_CAROUSEL,
        self::BODY_PART_CHART,
        self::BODY_PART_MAP,
        self::BODY_PART_VK,
    ];

    public const BODY_PARTS_WITH_URLS = [
        self::BODY_PART_VIDEO,
        self::BODY_PART_VIDEO_SOURCE,
        self::BODY_PART_SOUNDCLOUD,
        self::BODY_PART_IMAGE,
        self::BODY_PART_INSTAGRAM,
        self::BODY_PART_TWITTER,
        self::BODY_PART_FACEBOOK,
        self::BODY_PART_TELEGRAM,
        self::BODY_PART_MAP,
        self::BODY_PART_VK,
    ];

    public $articleType = null;
    public $sourceName = null;
    public $sourceDomain = null;

    private $indexer;
    private $restrictedWordsChecker;
    private $logger;
    private $queueManager;

    public $disablePushNotification = false;

    public function getMediaForTelegram(): array
    {
        return (new ArticleBody($this->body))->getMediaForTelegram();
    }

    public function __construct($config = [])
    {
        $this->restrictedWordsChecker = \Yii::$container->get(RestrictedWordsChecker::class);
        $this->indexer = \Yii::$container->get(ArticlesIndexer::class);
        $this->logger = \Yii::$container->get(Logger::class);
        $this->queueManager = \Yii::$container->get(QueueManager::class);

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'articles';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at', 'published_at']
            ],
            ActiveRecordUUIDBehavior::class,
            [
                'class' => yii\behaviors\SluggableBehavior::class,
                'ensureUnique' => true,
                'attribute' => 'title',
                'immutable' => true
            ]
        ];
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_INSERT
        ];
    }

    private function widgetFields(): array
    {
        return [
            'id',
            'title',
            'createdAt' => function () {
                return $this->created_at->toAtomString();
            },
            'sourceName' => function () {
                return $this->source->name;
            },
            'previewImage' => function () {
                if ($this->source_id === '6df8055e-2558-46a9-9bf5-d6657665a85f') {
                    return null;
                }
                return $this->preview_image;
            },
            'commentsCount' => function () {
                return count($this->comments);
            },
            'rating' => function () {
                return (int)$this->rating;
            }
        ];
    }

    public function fields()
    {
        if ($this->scenario === self::SCENARIO_WIDGET) {
            return $this->widgetFields();
        }

        $fields = [
            'id',
            'title',
            'link' => 'url',
            'createdAt' => function () {
                if (!Api::version(Api::V_2_0)) {
                    return $this->created_at->setTimezone('Europe/Kiev')->toDateTimeString();
                } else {
                    $date = $this->created_at;
//                    $date->setTimezone($this->source->timezone);
                    return $date->toAtomString();
                }
            },
            'previewImage' => function () {
                if ($this->source_id === '6df8055e-2558-46a9-9bf5-d6657665a85f') {
                    return null;
                }
                return $this->preview_image;
            },
            'categoryName' => 'category_name',
            'newsType' => function () {
                if ($this->source->type === Source::TYPE_PREVIEW) {
                    if (
                        Api::versionLessThan(Api::V_2_06) ||
                        (count($this->getPreparedBody()) === 1 && $this->previewImageUrl) ||
                        !count($this->getPreparedBody())
                    ) {
                        return Source::TYPE_WEBVIEW;
                    }
                }

                if (in_array($this->source->type, [Source::TYPE_YOUTUBE, Source::TYPE_YOUTUBE_PREVIEW], true) && Api::versionLessThan(Api::V_2_09)) {
                    return Source::TYPE_FULL_ARTICLE;
                }

                if ($this->source->type === Source::TYPE_YOUTUBE && Api::versionLessThan(Api::V_2_20)) {
                    return 'video';
                }

                if ($this->source->type === Source::TYPE_YOUTUBE_PREVIEW && Api::versionLessThan(Api::V_2_21)) {
                    if (Api::version(Api::V_2_20)) {
                        return Source::TYPE_YOUTUBE;
                    }

                    return 'video';
                }

                if (
                    in_array($this->source->type, [Source::TYPE_REDDIT, Source::TYPE_TWITTER, Source::TYPE_TELEGRAM], true) &&
                    Api::versionLessThan(Api::V_2_20)
                ) {
                    return Source::TYPE_FULL_ARTICLE;
                }

                return $this->source->type ?? $this->articleType;
            },
            'webviewJsIsEnabled' => function () {
                return (bool)$this->source->webview_js;
            },
            'sameArticleAmount' => function () {
                if (Api::version(Api::V_2_0)) {
                    return (int)$this->same_articles_amount;
                }

                return null;
            }
        ];

        if (Api::version(Api::V_2_0)) {
            if ($this->scenario !== self::SCENARIO_USER_PROFILE) {
                $fields['body'] = 'preparedBody';
            } else {
                $fields['body'] = static function () {
                    return null;
                };
            }

            $fields['sourceId'] = function () {
                return $this->source_id;
            };

            if (Api::version(Api::V_2_03)) {
                $fields['rating'] = function () {
                    return (int)$this->rating;
                };

                $fields['commentsEnabled'] = function () {
                    return (bool)$this->source->enable_comments;
                };

                $fields['commentsCount'] = function () {
                    return count($this->comments);
                };

                if (Api::version(Api::V_2_08)) {
                    $fields['sharesCount'] = function () {
                        return (int)$this->shares_count;
                    };
                }

                if (!(Yii::$app instanceof yii\console\Application)) {
                    $fields['currentRating'] = function () {
                        if (isset($this->currentUserRating[0])) {
                            return $this->currentUserRating[0]->rating;
                        }
                        return null;
                    };
                } else {
                    $fields['currentRating'] = static function () {
                        return null;
                    };
                }

                $fields['sharingLink'] = function () {
                    return $this->sharingUrl;
                };
            }
        }

        if (Api::version(Api::V_2_20)) {
            $fields['sourceUrlId'] = function () {
                return $this->source_url_id;
            };

            $fields['sourceName'] = function () {
                if ($this->source) {
                    return $this->source->name;
                }

                return $this->sourceName;
            };

            $fields['sourceDomain'] = function () {
                if ($this->sourceUrl) {
                    return $this->sourceUrl->getDomain();
                }
                return $this->sourceDomain;
            };
        }


        return $fields;
    }

    public function getSharingUrl(): string
    {
        if (!$this->id) {
            return Yii::$app->buzzUrlManager->createAbsoluteUrl('/');
        }

        if (defined('API_COUNTRY') && in_array(API_COUNTRY, ['RU', 'BY'])) {
            return $this->url;
        }


        return (Yii::$app->buzzUrlManager ?? Yii::$app->urlManager)->createAbsoluteUrl($this->sharingRoute);
    }

    public function getPreparedBody(): array
    {
        $body = array_values($this->body);

        if ($this->source->type !== Source::TYPE_PREVIEW) {
            return $body;
        }

        if ($this->preview_image) {
            array_unshift(
                $body,
                (new ArticleBodyNode(
                    self::BODY_PART_IMAGE,
                    $this->preview_image
                ))->jsonSerialize()
            );
        }

        return $body;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['source_id'], 'required'],
            [['created_at', 'updated_at', 'published_at'], TimestampValidator::class],
            [['enabled', 'banned_words', 'same_articles_amount', 'source_url_id'], 'integer'],
            [['body', 'description'], 'safe'],
            ['body', 'default', 'value' => []],
            [['source_id', 'category_id', 'same_article_id'], UUIDValidator::class],
            [['category_name'], 'string', 'max' => 50],
            [['title'], 'string', 'max' => 320],
            ['url', 'url'],
            ['preview_image', 'string'],
            [['url'], 'unique'],
//            ['slug', 'string']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'enabled' => 'Доступна',
            'created_at' => 'Дата добавления',
            'updated_at' => 'Updated At',
            'source_id' => 'Источник',
            'category_id' => 'Категория',
            'category_name' => 'Категория',
            'title' => 'Заголовок',
            'description' => 'Описание',
            'body' => 'Текст',
            'url' => 'Url',
            'preview_image' => 'Превью',
            'banned_words' => 'Banned Words',
            'clicks' => 'Клики',
            'views' => 'Просмотры',
            'comments_count' => 'Комментарии'
        ];
    }

    public static function instanceFromDto(ArticleItem $articleItem, ?Carbon $publicationDate = null): self
    {
        return new static([
            'created_at' => $publicationDate ?: Carbon::now('UTC'),
            'published_at' => $articleItem->getPublicationDate()->setTimezone('UTC'),
            'title' => $articleItem->getTitle(),
            'body' => $articleItem->getBody() ? $articleItem->getBody()->asArray() : [],
            'description' => $articleItem->getDescription(),
            'url' => $articleItem->getUrl(),
            'preview_image' => $articleItem->getPreviewImage(),
            'articleType' => $articleItem->getType(),
            'sourceName' => $articleItem->getSourceName(),
            'sourceDomain' => $articleItem->getSourceDomain()
        ]);
    }

    public static function createFromDto(ArticleItem $articleItem, SourceUrl $sourceUrl): bool
    {
        //TODO: ua-uk fix
        $restrictedLetters = '#[ыэъ]+#iu';
        if ($sourceUrl->source->country === 'UA' && $sourceUrl->default) {
            if (preg_match($restrictedLetters, $articleItem->getTitle())) {
                throw new \Exception();
            }
            foreach ($articleItem->getBody()->getNodes() as $node) {
                if (is_array($nodeValue = $node->getValue())) {
                    foreach ($nodeValue as $value) {
                        if (preg_match($restrictedLetters, $value)) {
                            throw new \Exception();
                        }
                    }
                } else {
                    if (preg_match($restrictedLetters, $node->getValue())) {
                        throw new \Exception();
                    }
                }
            }
        }

        $createdAt = null;

        if (!$sourceUrl->last_scraped_article_date) {
            $createdAt = $articleItem->getPublicationDate()->setTimezone('UTC');
        }

        $model = self::instanceFromDto($articleItem, $createdAt);
        if (!$sourceUrl->last_scraped_article_date) {
            $model->disablePushNotification = true;
        }
        $model->category_id = $sourceUrl->category_id;
        $model->category_name = $sourceUrl->category_name ?: $sourceUrl->category->name;
        $model->source_url_id = $sourceUrl->id;
        $model->source_id = $sourceUrl->source_id;

        return $model->save();
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            /** Проверка наличия "запрещенных" слов в статье */
            if ($this->restrictedWordsChecker->findBannedWordsInArticle($this)) {
                return false;
            }

            if ($this->restrictedWordsChecker->findInArticle($this)) {
                $this->banned_words = true;
            }

            /** Проверка новости на "одинаковость" с другой */
            try {
                if ($sameArticle = $this->indexer->findSameArticle($this)) {
                    $this->same_article_id = $sameArticle->id;
                    /** Увеличиваем счетчик "одинаковых" новостей */
                    $sameArticle->sameArticlesCounterUpdate();
                }
            } catch (\Throwable $e) {
                $this->logger->critical($e, [Logger::ELASTICSEARCH]);
            }
            return true;
        }

        return false;
    }

    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            /** Удаляем новость из индекса ElasticSearch */
            $this->indexer->delete($this);
            return true;
        }

        return false;
    }

    public function afterSave($insert, $changedAttributes)
    {
        /** Добавляем/обновляем новость в индекс ElasticSearch */
        if (!$this->same_article_id) {
            try {
                if (!$insert) {
                    $this->indexer->update($this);
                } else {
                    $this->indexer->add($this);
                }
            } catch (\Throwable $e) {
                $this->logger->critical($e, [Logger::ELASTICSEARCH]);
            }
        }

        parent::afterSave($insert, $changedAttributes);

        foreach ($this->source->sourcesToBeCopied as $sourceCopy) {
            $copyOfArticle = clone $this;
            $copyOfArticle->isNewRecord = true;
            $copyOfArticle->url .= (stristr($copyOfArticle->url, '?') ? '&' : '?') . substr($sourceCopy->id, 0, 8);
            $copyOfArticle->id = null;
            $copyOfArticle->source_id = $sourceCopy->id;
            $copyOfArticle->save(false);
        }

        if (!$this->disablePushNotification) {
            try {
                $this->queueManager->createArticlePushNotificationJob($this);
            } catch (\Throwable $e) {
                $this->logger->critical($e);
            }
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        $query = $this->hasOne(Category::class, ['id' => 'category_id']);
        if (defined('CURRENT_LANGUAGE')) {
            $query->localized(CURRENT_LANGUAGE);
        }

        return $query;
    }

    public function getSource()
    {
        return $this->hasOne(Source::class, [
            'id' => 'source_id'
        ]);
    }

    public function getSourceUrl()
    {
        return $this->hasOne(SourceUrl::class, [
            'id' => 'source_url_id'
        ]);
    }

    public function getShares()
    {
        return $this->hasMany(ArticleShare::class, [
            'article_id' => 'id'
        ]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRatings(): \common\queries\ArticleRating
    {
        return $this->hasMany(ArticleRating::class, ['article_id' => 'id']);
    }

    public function getPreviewImageUrl(): ?string
    {
        if ($this->source_id === '6df8055e-2558-46a9-9bf5-d6657665a85f') {
            return null;
        }
        if (!$this->preview_image) {
            return null;
        }

        return 'https://stx.myfresh.app/' . $this->preview_image;
    }

    public function getCurrentUserRating(): \common\queries\ArticleRating
    {
        return $this
            ->getRatings()
            ->byAppId(defined('API_APP_ID') ? API_APP_ID : null);
    }

    public function getComments()
    {
        /** @var \common\queries\Comment $query */
        $query = $this->hasMany(Comment::class, [
            'article_id' => 'id'
        ])->notDeleted();

        if (!(Yii::$app instanceof yii\console\Application)) {
            if (isset(Yii::$app->user) && (Yii::$app->user->isGuest || (\Yii::$app->user->identity instanceof App))) {
                $query = $query->enabled();
            } else {
                $query = $query->enabledOrForUser(\Yii::$app->user->identity);
            }
        }

        return $query;
    }

    public function getRatingValue(): ?int
    {
        return $this->rating;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAggregateStatistics()
    {
        return $this->hasMany(ArticlesStatistics::class, [
            'article_id' => 'id'
        ]);
    }

    public static function find()
    {
        return (new \common\queries\Article(get_called_class()));
    }

    /**
     * @param string $id
     * @return self|null
     */
    public static function findById(string $id): ?self
    {
        return self::find()->where(['id' => $id])->one(null, false);
    }

    /**
     * @param array $ids
     * @return self[]
     */
    public static function findByIds(array $ids): array
    {
        if (!count($ids)) {
            return [];
        }

        return self::find()->where(['id' => $ids])->all(null, false); // TODO: skip same
    }

    public function getBodyAsString(): string
    {
        $result = '';

        if (is_array($this->body)) {
            foreach ($this->body as $element) {
                if (in_array($element['elementName'], [self::BODY_PART_PARAGRAPH, self::BODY_PART_QUOTE], true)) {
                    $result .= ' ' . $element['value'];
                } elseif (is_array($element['value']) && in_array($element['elementName'], [self::BODY_PART_OL, self::BODY_PART_UL], true)) {
                    foreach ($element['value'] as $string) {
                        $result .= ' ' . $string;
                    }
                }
            }
        }

        return $result;
    }

    public function sameArticlesCounterUpdate($value = 1)
    {
        $this->updateCounters([
            'same_articles_amount' => $value
        ]);
    }

    public function getLocale(): ?string
    {
        return $this->source->locale ?? null;
    }

    public function isPostingToTelegramNeeded(): bool
    {
        return !$this->same_article_id && $this->source->telegram && $this->source->telegram_channel_id;
    }

    public function getMetaTitle()
    {
        $title = $this->title;

        if (preg_match('#-(\d{1,3})$#i', $this->slug, $m)) {
            $title .= ' ' . $m[1];
        }

        return $title;
    }
}
