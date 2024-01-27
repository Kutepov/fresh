<?php namespace api\models;

use common\components\caching\Cache;
use common\components\helpers\Api;
use common\models\App;
use yii\caching\TagDependency;
use yii\helpers\ArrayHelper;

class AdBanner extends \common\models\AdBanner
{
    public function fields()
    {
        $fields = [
            'type',
            'provider'
        ];

        if (in_array($this->type, [self::TYPE_CATEGORY, self::TYPE_FEED], true)) {
            $fields = ArrayHelper::merge($fields, [
                'position',
                'repeat_factor',
                'limit',
                'categories' => function () {
                    return $this->categories ?: null;
                }
            ]);
        }
        elseif (in_array($this->type, [self::TYPE_ARTICLE_BODY, self::TYPE_SIMILAR_ARTICLES])) {
            $fields[] = 'banner_id';
            $fields[] = 'position';
        }
        elseif ($this->type === self::TYPE_FULLSCREEN) {
            $fields[] = 'banner_id';
        }

        return $fields;
    }

    public static function getBannersFor(?string $platform, ?string $country): array
    {
        $banners = self::find()
            ->forPlatform($platform)
            ->forCountry($country);

        if (Api::versionLessThan(Api::V_2_04)) {
            $banners->andWhere([
                '<>', 'type', self::TYPE_ARTICLE_BODY
            ]);
        }

        if (Api::versionLessThan(Api::V_2_05)) {
            $banners->andWhere([
                '<>', 'type', self::TYPE_FULLSCREEN
            ]);
        }

        if (Api::versionLessThan(Api::V_2_05)) {
            $banners->andWhere([
                '<>', 'type', self::TYPE_SIMILAR_ARTICLES
            ]);
        }

        $banners = $banners
            ->cache(
                Cache::DURATION_AD_BANNERS,
                new TagDependency([
                    'tags' => Cache::TAG_AD_BANNERS_LIST
                ])
            )->all();

        if (defined('API_APP_VERSION') && defined('API_PLATFORM')) {
            if (API_PLATFORM === App::PLATFORM_ANDROID && version_compare(API_APP_VERSION, '4.6.96', '>=')) {
                $banners = array_map(static function (AdBanner $banner) {
                    if ($banner->banner_id === 'ca-app-pub-7635126548465920/9204734734') {
                        $banner->banner_id = 'ca-app-pub-7635126548465920/4171735873';
                    }
                    if ($banner->banner_id === 'ca-app-pub-7635126548465920/4227740229') {
                        $banner->banner_id = 'ca-app-pub-7635126548465920/1564656355';
                    }
                    return $banner;
                }, $banners);
            }

            if (API_PLATFORM === App::PLATFORM_IOS && version_compare(API_APP_VERSION, '2.5.10', '>=')) {
                $banners = array_map(static function (AdBanner $banner) {
                    if ($banner->banner_id === 'ca-app-pub-7635126548465920/9480066909') {
                        $banner->banner_id = 'ca-app-pub-7635126548465920/9680364806';
                    }
                    if ($banner->banner_id === 'ca-app-pub-7635126548465920/2734872767') {
                        $banner->banner_id = 'ca-app-pub-7635126548465920/7054201466';
                    }
                    return $banner;
                }, $banners);
            }
        }

        return $banners;
    }

    public static function find()
    {
        return parent::find()->enabled();
    }
}