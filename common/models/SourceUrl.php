<?php namespace common\models;

use Carbon\Carbon;
use common\components\helpers\Api;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\ArticlesListScraper;
use common\components\scrapers\common\Scraper;
use common\components\validators\TimestampValidator;
use common\models\pivot\HashtagSourceUrl;
use common\services\SourcesService;
use common\services\SourcesUrlsService;
use voskobovich\linker\LinkerBehavior;
use Yii;
use common\behaviors\CarbonBehavior;
use backend\models\Country;

/**
 * This is the model class for table "sources_urls".
 *
 * @property integer $id
 * @property bool $default
 * @property string|null $class
 * @property boolean $enabled
 * @property Carbon $locked_at
 * @property integer $lock_id
 * @property string $timezone
 * @property boolean $ios_enabled
 * @property boolean $android_enabled
 * @property string $url
 * @property string|null $alias
 * @property string $category_id
 * @property string $source_id
 * @property string $category_name
 * @property Carbon $last_scraped_at
 * @property Carbon $last_scraped_article_date
 * @property integer $avg_news_freq
 * @property string $note
 * @property boolean $last_scraped_article_date_disabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $folder_id
 * @property null|string $name
 * @property int|boolean $enable_comments
 * @property string $urlId
 * @property string|null $url_skip_regexp
 * @property int|null $subscribers_count
 * @property int[] $countries_ids
 *
 * @property-read Category $category
 * @property-read Source $source
 * @property-read SourceUrlLock $currentLock
 * @property-read Country[] $countries
 *
 * @property Scraper|ArticleBodyScraper|ArticlesListScraper $scraper
 */
class SourceUrl extends \yii\db\ActiveRecord
{
    /** Scraper|ArticleBodyScraper|ArticlesListScraper|null */
    private $_scraper = null;
    public $image;
    public $type;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sources_urls';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => [
                    'last_scraped_at',
                    'locked_at',
                    'last_scraped_article_date',
                    'created_at',
                    'updated_at',
                ]
            ],
            [
                'class' => LinkerBehavior::class,
                'relations' => [
                    'countries_ids' => 'countries'
                ]
            ]
        ];
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_UPDATE
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['enabled', 'ios_enabled', 'android_enabled', 'last_scraped_article_date_disabled', 'default'], 'boolean'],
            ['class', 'string', 'max' => 320],
            ['alias', 'string', 'max' => 320],
            ['timezone', 'string', 'max' => 48],
            ['timezone', 'in', 'range' => Country::getTimezonesForDropdown()],
            ['lock_id', 'integer'],
            [['last_scraped_at', 'locked_at', 'last_scraped_article_date'], TimestampValidator::class],
            ['url', 'unique', 'message' => 'Такой url уже добавлен'],
            [['url'], 'url'],
            [['category_id', 'source_id'], 'string', 'max' => 36],
            [['category_name'], 'string', 'max' => 160],
            [['category_id'], 'exist', 'skipOnError' => true, 'targetClass' => Category::class, 'targetAttribute' => ['category_id' => 'id']],
            [['source_id'], 'exist', 'skipOnError' => true, 'targetClass' => Source::class, 'targetAttribute' => ['source_id' => 'id']],
            [['note', 'name'], 'string'],
            [['source_id', 'category_id', 'class', 'url'], 'required'],
            [['timezone'], 'required', 'when' => function (SourceUrl $sourceUrl) {
                return !$sourceUrl->source || !$sourceUrl->source->timezone;
            }, 'enableClientValidation' => false],
            ['enable_comments', 'default', 'value' => true],
            ['enable_comments', 'boolean'],
            ['countries_ids', 'each', 'rule' => ['integer']]
        ];
    }

    public function fields()
    {
        $fields = [
            'id',
            'category_id',
            'source_id',
            'image' => function () {
                if ($this->image) {
                    return $this->image;
                }

                if (!$this->source->image) {
                    return null;
                }

                return Yii::$app->urlManager->createAbsoluteUrl('/img/' . $this->source->image);
            },
            'alias',
            'title' => function () {
                return $this->name ?: $this->source->name ?: $this->category->title ?: null;
            },
            'domain' => function () {
                return $this->getDomain();
            },
            'url',
            'type' => function () {
                return $this->type ?: $this->source->type;
            }
        ];

        if (Api::version(Api::V_2_20)) {
            $fields['rss'] = function () {
                return (bool)$this->source->rss;
            };
            $fields['source_name'] = function () {
                return $this->source->name;
            };
            $fields['enable_comments'] = function () {
                return (bool)$this->enable_comments;
            };
            $fields['recommended'] = function () {
                return (bool)$this->source->default;
            };

            $fields['image_url'] = function () {
                if ($this->image) {
                    return $this->image;
                }

                if ($this->source) {
                    return $this->source->getImageUrl();
                }

                return null;
            };
            $fields['language_migration_source_id'] = function () {
                $service = Yii::$container->get(SourcesService::class);
                if ($this->source_id && $this->source && $this->source->country === 'UA') {
                    return $service->getFallbackRuSourceIdForUkSource($this->source_id);
                }
                return null;
            };
            $fields['language_migration_id'] = function () {
                $service = Yii::$container->get(SourcesUrlsService::class);
                if ($this->source_id && $this->source && $this->source->country === 'UA') {
                    return $service->getFallbackRuSourceUrlIdForUkSourceUrl($this->id);
                }
                return null;
            };
        }

        if (Api::version(Api::V_2_23)) {
            $fields[] = 'folder_id';
        }

        return $fields;
    }

    /**
     * @param false $debug
     * @return Scraper|null
     * @return Scraper|ArticleBodyScraper|ArticlesListScraper
     * @throws \yii\base\InvalidConfigException
     */
    public function getScraper($debug = false): ?Scraper
    {
        if (is_null($this->_scraper) && !is_null($this->class)) {
            $tz = new \DateTimeZone($this->timezone);
            if (!$this->last_scraped_article_date) {
                if (!$this->source->default) {
                    $lastPublicationTime = Carbon::parse('90 days ago', $tz);
                } else {
                    $lastPublicationTime = Carbon::parse('today', $tz);
                }
            } else {
                $lastPublicationTime = $this->last_scraped_article_date->setTimezone($tz);
            }

            /** @var Scraper _scraper */
            $this->_scraper = Yii::createObject([
                'class' => $this->class,
                'id' => $this->id,
                'sourceId' => $this->source_id,
                'timezone' => $tz,
                'url' => $this->url,
                'lastPublicationTime' => $lastPublicationTime,
                'debug' => $debug,
                'urlSkipRegexp' => $this->url_skip_regexp
            ]);
        }

        return $this->_scraper;
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSource()
    {
        return $this->hasOne(Source::class, ['id' => 'source_id']);
    }

    public function beforeSave($insert)
    {
        /** Дефолтный часовой пояс берем из основного источника */
        if (!$this->timezone) {
            $this->timezone = $this->source->timezone;
        }

        if ($this->category->folder) {
            $this->folder_id = $this->category->folder->id;
        } else {
            $this->folder_id = null;
        }

        return parent::beforeSave($insert);
    }

    public static function find()
    {
        return new \common\queries\SourceUrl(get_called_class());
    }

    public function lockForScraping(): void
    {
        $lock = new SourceUrlLock([
            'source_id' => $this->source_id,
            'source_url_id' => $this->id,
            'locked_at' => $time = Carbon::now()
        ]);

        $lock->save();

        $this->locked_at = $time;
        $this->lock_id = $lock->id;
        $this->save();
    }

    public function unlockForScrapingByCron(): void
    {
        $this->unlockForScraping(1, 0, true);
    }

    public function unlockForScraping(bool $errors, int $articlesFound = 0, bool $byCron = false): void
    {
        $time = Carbon::now();

        if ($lock = $this->currentLock) {
            $lock->updateAttributes([
                'unlocked_at' => $time,
                'lock_time' => $time->diff($lock->locked_at)->format('%H:%I:%S'),
                'errors' => $errors,
                'articles_found' => $articlesFound,
                'unlocked_by_cron' => $byCron
            ]);
        }

        $this->updateAttributes([
            'locked_at' => null,
            'lock_id' => null,
            'last_scraped_at' => $time
        ]);
    }

    public function getCurrentLock()
    {
        return $this->hasOne(SourceUrlLock::class, [
            'id' => 'lock_id'
        ]);
    }

    public function getHashtagsSourcesUrls()
    {
        return $this->hasMany(HashtagSourceUrl::class, [
            'source_url_id' => 'id'
        ]);
    }

    public function getDomain(): string
    {
        if ($this->source) {
            return $this->source->getDomain();
        }
        return extractDomainFromUrl($this->url);
    }

    public function getHashtags()
    {
        return $this->hasMany(Hashtag::class, [
            'id' => 'hashtag_id'
        ])->via('hashtagsSourcesUrls');
    }

    public function updateLastScrapedArticleDate(?Carbon $date = null): void
    {
        if (is_null($date)) {
            $date = Carbon::now();
        }

        if ($this->last_scraped_article_date_disabled) {
            $date = null;
        }

        $this->updateAttributes(['last_scraped_article_date' => $date]);
    }
    public function getCountries()
    {
        return $this->hasMany(\common\models\Country::class, [
            'id' => 'country_id'
        ])->viaTable('sources_urls_countries', [
            'source_url_id' => 'id'
        ]);
    }
}
