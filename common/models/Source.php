<?php namespace common\models;

use Carbon\Carbon;
use common\behaviors\ActiveRecordUUIDBehavior;
use common\behaviors\CarbonBehavior;
use common\components\caching\Cache;
use common\components\helpers\Api;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\ArticlesListScraper;
use common\components\scrapers\common\Scraper;
use common\components\validators\UUIDValidator;
use voskobovich\linker\LinkerBehavior;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "sources".
 *
 * @property string $id
 * @property string $group_id
 * @property boolean $enabled
 * @property string $timezone
 * @property boolean $ios_enabled
 * @property boolean $android_enabled
 * @property string $name
 * @property string $url
 * @property string $image
 * @property string $external_image_url
 * @property string $country
 * @property string $language
 * @property string $type
 * @property integer $webview_js
 * @property integer $banned_top
 * @property integer $avg_news_freq
 * @property string $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $injectable_css
 * @property string $injectable_js
 * @property string $adblock_css_selectors
 * @property boolean $processed
 * @property boolean $telegram
 * @property boolean $push_notifications
 * @property string|null $telegram_channel_id
 * @property string $copy_from_source_id
 * @property int $rss
 * @property-read string $locale
 * @property int|bool $default если true - источник создан нами, false - добавлен юзером
 * @property int|boolean $enable_comments включены ли комменты для источника
 * @property boolean $use_publication_date
 * @property int|null $subscribers_count
 * @property int[] $countries_ids
 *
 * @property SourceUrl[] $urls
 * @property-read Country[] $countries
 * @property-read Country $countryModel
 * @property-read string[] $countriesCodes
 * @property-read Language $languageModel
 * @property-read \common\models\Source[] $sourcesToBeCopied
 *
 * @property-read bool $isParsedArticlesBodies
 *
 * @property Scraper|ArticleBodyScraper|ArticlesListScraper $scraper
 */
class Source extends \yii\db\ActiveRecord
{
    public const SCENARIO_GROUPED = 'grouped';

    public const TYPE_PREVIEW = 'preview';
    public const TYPE_WEBVIEW = 'webview';
    public const TYPE_BROWSER = 'browser';
    public const TYPE_YOUTUBE = 'youtube';
    public const TYPE_YOUTUBE_PREVIEW = 'youtube-preview';
    public const TYPE_FULL_ARTICLE = 'full-news-item';
    public const TYPE_TELEGRAM = 'telegram';
    public const TYPE_TWITTER = 'twitter';
    public const TYPE_REDDIT = 'reddit';
    
    public const AVAILABLE_TYPES = [
        self::TYPE_PREVIEW,
        self::TYPE_WEBVIEW,
        self::TYPE_BROWSER,
        self::TYPE_YOUTUBE,
        self::TYPE_YOUTUBE_PREVIEW,
        self::TYPE_FULL_ARTICLE,
        self::TYPE_TELEGRAM,
        self::TYPE_TWITTER,
        self::TYPE_REDDIT
    ];

    public const SITE_RSS_TYPES = [
        self::TYPE_PREVIEW,
        self::TYPE_WEBVIEW,
        self::TYPE_BROWSER,
        self::TYPE_FULL_ARTICLE
    ];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sources';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at']
            ],
            ActiveRecordUUIDBehavior::class,
            [
                'class' => LinkerBehavior::class,
                'relations' => [
                    'countries_ids' => 'countries'
                ]
            ]
        ];
    }

    public function fields()
    {
        $fields = [
            'id',
            'name',
            'image',
            'newsType' => function () {
                if ($this->type === self::TYPE_PREVIEW && Api::versionLessThan(Api::V_2_06)) {
                    return self::TYPE_WEBVIEW;
                }

                if (in_array($this->type, ['video', Source::TYPE_YOUTUBE, Source::TYPE_YOUTUBE_PREVIEW], true) && Api::versionLessThan(Api::V_2_09)) {
                    return self::TYPE_FULL_ARTICLE;
                }

                if (in_array($this->type, [Source::TYPE_YOUTUBE, Source::TYPE_YOUTUBE_PREVIEW], true) && Api::versionLessThan(Api::V_2_20)) {
                    return 'video';
                }

                if (
                    in_array($this->type, [Source::TYPE_REDDIT, Source::TYPE_TWITTER, Source::TYPE_TELEGRAM], true) &&
                    Api::versionLessThan(Api::V_2_20)
                ) {
                    return Source::TYPE_FULL_ARTICLE;
                }

                return $this->type;
            },
            'webviewJsIsEnabled' => function () {
                return (bool)$this->webview_js;
            }
        ];

        if (Api::version(Api::V_2_0)) {
            if (Api::version(Api::V_2_01)) {
                $fields['groupId'] = 'group_id';
            }

            if (Api::version(Api::V_2_02)) {
                $fields[] = 'url';
            }

            if (Api::versionLessThan(Api::V_2_02) || Api::version(Api::V_2_20)) {
                $fields['domain'] = function () {
                    return $this->getDomain();
                };
            }
        }

        if (Api::version(Api::V_2_03)) {
            $fields['jsInjection'] = function () {
                $injection = null;

                if (!empty($this->injectable_js)) {
                    $injection = trim($this->injectable_js, ';') . ';';
                }

                if ($this->injectable_css) {
                    $injection .= "
                       var styleTag = document.getElementById('fresh-app-custom-css');
                       if (!styleTag) {
                            styleTag = document.createElement('style');
                            styleTag.id = 'fresh-app-custom-css';
                            document.documentElement.appendChild(styleTag);
                       }
                       styleTag.textContent = '" . addcslashes($this->injectable_css, "'") . "';
                    ";
                }

                $injection = rmnl(trim($injection));

                if (empty($injection)) {
                    return null;
                }

                return $injection;
            };
        }

        if (Api::version(Api::V_2_20)) {
            $fields['rss'] = function () {
                return (bool)$this->rss;
            };

            $fields['enable_comments'] = function () {
                return (bool)$this->enable_comments;
            };

            $fields['image_url'] = function () {
                return $this->getImageUrl();
            };
        }

        return $fields;
    }

    public function getImageUrl(): ?string
    {
        if ($this->external_image_url) {
            return $this->external_image_url;
        }

        if ($this->image) {
            return \Yii::$app->urlManager->createAbsoluteUrl('/img/' . $this->image);
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['injectable_css', 'adblock_css_selectors'], 'trim'],
            [['enabled', 'ios_enabled', 'android_enabled', 'default', 'use_publication_date'], 'boolean'],
            [['webview_js', 'banned_top'], 'integer'],
            ['url', 'url'],
            [['name', 'type'], 'string', 'max' => 64],
            ['timezone', 'string', 'max' => 48],
            [['image'], 'string', 'max' => 320],
            [['country'], 'string', 'max' => 2],
            ['language', 'string', 'max' => 5],
            ['note', 'string'],
            ['group_id', UUIDValidator::class],
            [['name', 'type', 'timezone', 'url'], 'required'],
            [['language'], 'required', 'on' => self::SCENARIO_GROUPED],
            ['enable_comments', 'default', 'value' => true],
            ['enable_comments', 'boolean'],
            ['external_image_url', 'url', 'skipOnError' => true],
            ['countries_ids', 'each', 'rule' => ['integer']]
        ];
    }

    public function getWebkitContentRules(): ?array
    {
        if (!empty($this->adblock_css_selectors)) {
            $domain = parse_url($this->url, PHP_URL_HOST);
            $domain = preg_replace('#^www\.#', '', $domain);

            return [
                'trigger' => [
                    'url-filter' => '.*',
                    'if-domain' => [
                        '*' . $domain
                    ]
                ],
                'action' => [
                    'type' => 'css-display-none',
                    'selector' => implode(',', array_map('trim', explode("\n", $this->adblock_css_selectors)))
                ]
            ];
        }

        return null;
    }

    public function getUrls()
    {
        return $this->hasMany(SourceUrl::class, [
            'source_id' => 'id'
        ]);
    }

    public function getCountryModel()
    {
        return $this->hasOne(Country::class, [
            'code' => 'country'
        ]);
    }

    public function getCountries()
    {
        return $this->hasMany(Country::class, [
            'id' => 'country_id'
        ])->viaTable('sources_countries', [
            'source_id' => 'id'
        ]);
    }

    public function getCountriesCodes(): array
    {
        return ArrayHelper::getColumn($this->countries, 'code');
    }

    public function getLanguageModel()
    {
        return $this->hasOne(Language::class, [
            'code' => 'language'
        ]);
    }

    public function afterFind()
    {
        if ($this->group_id) {
            $this->scenario = self::SCENARIO_GROUPED;
        }
        parent::afterFind();
    }


    public static function find()
    {
        return new \common\queries\Source(get_called_class());
    }

    public function getLocale(): string
    {
        if ($this->language) {
            return strtolower($this->languageModel->locale);
        }

        return strtolower($this->countries[0]->locale);
    }

    public function getIsParsedArticlesBodies(): bool
    {
        return $this->type === 'full-news-item';
    }

    public function getSourcesToBeCopied(): ActiveQuery
    {
        return $this->hasMany(self::class, [
            'copy_from_source_id' => 'id'
        ]);
    }

    public function getDomain(): string
    {
        return extractDomainFromUrl($this->url);
    }

    public function afterSave($insert, $changedAttributes)
    {
        Cache::clearByTag(Cache::TAG_SOURCES_LIST);
        foreach ($this->urls as $url) {
            if ($url->default != $this->default) {
                $url->updateAttributes(['default' => $this->default]);
            }
        }
        parent::afterSave($insert, $changedAttributes);
    }
}
