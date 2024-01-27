<?php namespace common\components\caching;

use yii\caching\TagDependency;

class Cache
{
    public const DURATION_LANGUAGES_LIST = YII_DEBUG ? 300 : 3600;
    public const TAG_LANGUAGES_LIST = 'languagesList';

    public const DURATION_COUNTRY = YII_DEBUG ? 300 : 3600;
    public const TAG_COUNTRY = 'country';

    public const DURATION_SOURCES_LIST = YII_DEBUG ? 300 : 86400 / 2;
    public const TAG_SOURCES_LIST = 'sourcesList';

    public const DURATION_CATEGORIES_LIST = YII_DEBUG ? 300 : 86400 / 2;
    public const TAG_CATEGORIES_LIST = 'categoriesList';

    public const DURATION_COUNTRIES_LIST = YII_DEBUG ? 300 : 86400 / 2;
    public const TAG_COUNTRIES_LIST = 'countriesList';

    public const DURATION_AD_BANNERS = YII_DEBUG ? 300 : 86400 / 2;
    public const TAG_AD_BANNERS_LIST = 'adBannersList';

    public const DURATION_DAILY_STATISTICS = 900;
    public const TAG_DAILY_STATISTICS = 'dailyStatistics';

    public static function clearByTag($tag): void
    {
        TagDependency::invalidate(\Yii::$app->cache, $tag);
    }
}