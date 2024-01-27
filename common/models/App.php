<?php namespace common\models;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Carbon\Carbon;
use common\components\caching\Cache;
use common\components\validators\TimestampValidator;
use common\models\pivot\SourceSubscription;
use common\models\pivot\SourceUrlSubscription;
use common\services\SourcesService;
use common\services\SourcesUrlsService;
use Yii;
use yii\base\NotSupportedException;
use yii\base\UserException;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\validators\DateValidator;
use yii\web\IdentityInterface;
use yii2mod\behaviors\CarbonBehavior;

/**
 * This is the model class for table "apps".
 *
 * @property integer $id
 * @property integer $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $date
 * @property string $country
 * @property string $language
 * @property string $articles_language
 * @property $ip
 * @property string $device_id
 * @property string $platform
 * @property string $version
 * @property boolean|int $push_notifications
 * @property string $push_token
 * @property \common\models\User|null $user
 * @property $enabled_sources
 * @property $enabled_sources_urls
 * @property $enabled_categories
 * @property boolean $banned
 * @property string $preview_type
 * @property boolean|int $pro
 * @property Carbon|null $pro_started_at
 * @property Carbon|null $pro_expires_at
 * @property boolean|int $sources_subscriptions_processed
 *
 * @property-read bool $isIos
 * @property-read bool $isAndroid
 * @property-read bool $isWeb
 * @property-read \common\models\Country $countryModel
 *
 * @property-read SourceUrlSubscription[] $sourcesUrlsSubscriptions
 * @property-read SourceSubscription[] $sourcesSubscriptions
 * @property-read SourceUrl[] $sourcesUrls
 * @property-read Source[] $sources
 **/
class App extends \yii\db\ActiveRecord implements IdentityInterface
{
    public const PLATFORM_IOS = 'ios';
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_WEB = 'web';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'apps';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at', 'pro_started_at', 'pro_expires_at']
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['push_token', 'string'],
            [['push_notifications', 'pro'], 'boolean'],
            [['enabled_sources', 'enabled_sources_urls', 'enabled_categories'], 'safe'],
            [['created_at', 'updated_at', 'pro_started_at', 'pro_expires_at'], TimestampValidator::class],
            ['date', 'date', 'format' => 'php:Y-m-d'],
            ['ip', 'string', 'max' => 39],
            ['version', 'string', 'max' => 16],
            [['language', 'articles_language'], 'string', 'max' => 5],
            [['country'], 'string', 'max' => 2],
            [['device_id'], 'string', 'max' => 255],
            [['platform'], 'string', 'max' => 12],
            [['device_id', 'platform'], 'required'],
            ['user_id', 'integer'],
            ['banned', 'boolean'],
            ['sources_subscriptions_processed', 'boolean'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            ['preview_type', 'in', 'range' => Country::PREVIEW_TYPES]
//            [['device_id', 'platform'], 'unique', 'targetAttribute' => ['device_id', 'platform']]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created_at' => 'Created At',
            'country' => 'Country',
            'device_id' => 'Device ID',
            'platform' => 'Platform',
            'version' => 'Version'
        ];
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->date = date('Y-m-d');
            } else {
                if ($this->isAttributeChanged('enabled_sources')) {
                    $this->prepareSources();
                }

                if ($this->isAttributeChanged('enabled_sources_urls')) {
                    $this->prepareSourcesUrls();
                }

                if ($this->isAttributeChanged('enabled_categories')) {
                    $this->prepareCategories();
                }
            }
            return true;
        }

        return false;
    }

    private function prepareSources(): void
    {
        $sourcesService = Yii::$container->get(SourcesService::class);

        $this->enabled_sources = $sourcesService->getFilteredSourcesIds(
            $this->enabled_sources,
            $this->country,
            $this->articles_language
        ) ?: [];
    }

    private function prepareSourcesUrls(): void
    {
        $sourcesService = Yii::$container->get(SourcesUrlsService::class);

        $this->enabled_sources_urls = $sourcesService->getFilteredSourcesUrlsIds(
            $this->enabled_sources_urls,
            $this->country,
            $this->articles_language
        ) ?: [];
    }

    private function prepareCategories(): void
    {
        $this->enabled_categories = Category::find()
            ->select('name')
            ->bySlugName($this->enabled_categories)
            ->cache(
                Cache::DURATION_CATEGORIES_LIST,
                new TagDependency(['tags' => Cache::TAG_CATEGORIES_LIST])
            )
            ->column();
    }

    public function getIsIos(): bool
    {
        return $this->platform === self::PLATFORM_IOS;
    }

    public function getIsAndroid(): bool
    {
        return $this->platform === self::PLATFORM_ANDROID;
    }

    public function getIsWeb(): bool
    {
        return $this->platform === self::PLATFORM_WEB;
    }

    public static function find()
    {
        return new \common\queries\App(get_called_class());
    }

    /**
     * @throws AssertionFailedException
     * @throws UserException
     */
    public function enablePushNotifications(string $token, array $sourcesUrls = []): void
    {
        if (empty(trim($token))) {
            throw new UserException('Token cannot be empty.');
        }

        Assertion::isArray($sourcesUrls);

        if (!$sourcesUrls) {
            $this->updateAttributes([
                'push_notifications' => 1,
                'push_token' => $token
            ]);
        }
        elseif ($this->pro) {
            $this->updateAttributes([
                'push_token' => $token
            ]);
            SourceUrlSubscription::updateAll([
                'push' => 1
            ], [
                'app_id' => $this->id,
                'source_url_id' => $sourcesUrls
            ]);
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function disablePushNotifications(array $sourcesUrls = []): void
    {
        Assertion::isArray($sourcesUrls);

        if (!$sourcesUrls) {
            $this->updateAttributes([
                'push_notifications' => 0
            ]);
        }
        else {
            SourceUrlSubscription::updateAll([
                'push' => 0
            ], [
                'app_id' => $this->id,
                'source_url_id' => $sourcesUrls
            ]);
        }
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, [
            'id' => 'user_id'
        ]);
    }

    public static function findIdentity($id)
    {
        return self::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    public function getId()
    {
        return $this->getPrimaryKey();
    }

    public function getAuthKey()
    {
        return $this->device_id;
    }

    public function validateAuthKey($authKey)
    {
        return $this->device_id === $authKey;
    }

    public function getCountryModel()
    {
        return $this->hasOne(Country::class, [
            'code' => 'country'
        ]);
    }

    public static function findByUUID($uuid, ?string $platform = null)
    {
        return self::find()
            ->filterWhere([
                'device_id' => $uuid,
                'platform' => $platform
            ])
            ->one();
    }

    public function getSourcesSubscriptions(): ActiveQuery
    {
        return $this->hasMany(SourceSubscription::class, [
            'app_id' => 'id'
        ]);
    }

    public function getSourcesUrlsSubscriptions(): ActiveQuery
    {
        return $this->hasMany(SourceUrlSubscription::class, [
            'app_id' => 'id'
        ]);
    }

    public function getSources(): ActiveQuery
    {
        return $this->hasMany(Source::class, [
            'id' => 'source_id'
        ])->via('sourcesSubscriptions');
    }

    public function getSourcesUrls(): ActiveQuery
    {
        return $this->hasMany(SourceUrl::class, [
            'id' => 'source_url_id'
        ])->via('sourcesUrlsSubscriptions');
    }

    public function isSubscribedToSourceUrl(SourceUrl $sourceUrl): bool
    {
        return $this->getSourcesUrlsSubscriptions()
            ->andWhere(['source_url_id' => $sourceUrl->id])
            ->exists();
    }

    public function isSubscribedToSource(Source $source): bool
    {
        return $this->getSourcesSubscriptions()
            ->andWhere(['source_id' => $source->id])
            ->exists();
    }
}
