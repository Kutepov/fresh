<?php namespace common\services;

use common\components\caching\Cache;
use common\components\scrapers\common\RssScraper;
use common\models\Category;
use common\models\Country;
use common\models\Source;
use common\models\SourceUrl;
use yii\caching\TagDependency;
use yii\helpers\ArrayHelper;

class SourcesService
{
    private DbManager $dbManager;
    private QueueManager $queueManager;

    public function __construct()
    {
        $this->dbManager = \Yii::$container->get(DbManager::class);
        $this->queueManager = \Yii::$container->get(QueueManager::class);
    }

    /**
     * @param array $sourcesIds
     * @param string|null $forCountry
     * @param string|null $forLanguage
     * @return array|bool
     */
    public function getFilteredSourcesIds(array $sourcesIds, ?string $forCountry = null, ?string $forLanguage = null)
    {
        $sourcesIds = array_values($sourcesIds);
        $enabledSourcesIds = $this->getEnabledSourcesIds($forCountry, $forLanguage);

        if (!count($enabledSourcesIds)) {
            return false;
        }

        if (!count($sourcesIds)) {
            return $enabledSourcesIds;
        }

        return array_values(array_intersect($sourcesIds, $enabledSourcesIds)) ?: false;
    }

    /**
     * @param string|null $forCountry
     * @param string|null $forLanguage
     * @return array
     */
    public function getEnabledSourcesIds(?string $forCountry = null, ?string $forLanguage = null): array
    {
        $enabledSources = $this->getEnabledSources($forCountry, $forLanguage);

        return ArrayHelper::getColumn($enabledSources, 'id');
    }

    /**
     * @param string|null $forCountry
     * @param string|null $forLanguage
     * @return Source[]
     */
    public function getEnabledSources(?string $forCountry = null, ?string $forLanguage = null): array
    {
        return Source::find()
            ->enabled()
            ->defaultOnly()
            ->byCountry($forCountry)
            ->byLanguage($forLanguage)
            ->orderBy(['sources.name' => SORT_ASC])
            ->cache(
                Cache::DURATION_SOURCES_LIST,
                new TagDependency([
                    'tags' => Cache::TAG_SOURCES_LIST
                ])
            )
            ->all();
    }

    public function createSource($type, $name, $homeUrl, $url, $className, $imageUrl = null, ?string $countryCode = null, ?string $categoryId = null, $default = true): ?SourceUrl
    {
        $source = new Source([
            'type' => $type,
            'enabled' => true,
            'default' => $default,
            'name' => $name,
            'external_image_url' => $imageUrl,
            'url' => $homeUrl,
            'timezone' => 'UTC',
            'enable_comments' => false,
            'rss' => $className === RssScraper::class
        ]);

        if ($countryCode && ($country = Country::findByCode($countryCode))) {
            $source->countries_ids = [$country->id];
            $source->country = $countryCode;
        }

        if ($source->save(false)) {
            $sourceUrlModel = new SourceUrl([
                'class' => $className,
                'name' => $name,
                'enabled' => true,
                'default' => $default,
                'category_id' => $categoryId ?: Category::DEFAULT_CATEGORY_ID,
                'source_id' => $source->id,
                'timezone' => $source->timezone,
                'url' => $url,
                'enable_comments' => false
            ]);

            $sourceUrlModel->countries_ids = $source->countries_ids;

            if ($sourceUrlModel->save(false)) {
                $this->queueManager->createSourceUrlFirstTimeParsingJob($sourceUrlModel);
                return $sourceUrlModel;
            }
        }

        return null;
    }

    private const MAPPING = [
        '5263c20e-8584-11eb-8907-0242ac1f0005' => '0006c61e-6f95-11eb-9cce-0242ac1f0003',
        '57010fe2-8584-11eb-9c68-0242ac1f0005' => '032cdbfa-13e2-4747-aa2d-82f4fd65584e',
        '62aede8c-8584-11eb-8cdb-0242ac1f0005' => '2629fce5-67d8-4d4a-8e26-e93a9f8f11ec',
        '6394bc5e-8584-11eb-9e0e-0242ac1f0005' => '265cc183-40bc-48e1-ada0-3b671c9eea04',
        '6561027c-8584-11eb-bac6-0242ac1f0005' => '31fd5bce-6483-4d4c-97cc-998fb3e62d99',
        '6514439c-8584-11eb-93f3-0242ac1f0005' => '456c3821-4af2-4fc4-ab4a-c6bfc79fc4c2',
        '62fb9452-8584-11eb-8ef7-0242ac1f0005' => '807f3102-ea28-465a-875e-fd7e31d6cac4',
        '616ebfec-8584-11eb-9acc-0242ac1f0005' => '9d8d177a-fc8f-4692-b322-8a0f7ff7a26b',
        '62186880-8584-11eb-9ac4-0242ac1f0005' => 'c0fc13b1-d834-4738-8ce2-d429bfcafc4a',
        '63e135a2-8584-11eb-bf26-0242ac1f0005' => 'c3046cbc-06cf-4407-9658-335862b6de93',
        'c7503eab-6807-462b-8866-891a037bbb61' => 'c7503eab-6807-462b-8866-891a037bbb66',
        '6347f4c8-8584-11eb-b2e0-0242ac1f0005' => 'd305cbb8-2991-4a97-87a1-ba427b03b2a1'
    ];

    public function convertUaRuSourcesIdsToUkIfNeeded(array $sourcesIds): array
    {
        $result = [];

        foreach ($sourcesIds as $sourceId) {
            if (isset(self::MAPPING[$sourceId]) && self::MAPPING[$sourceId]) {
                $result[] = self::MAPPING[$sourceId];
            } else {
                $result[] = $sourceId;
            }
        }

        return $result;
    }

    public function convertUaRuSourcesIdToUkIfNeeded(string $sourceId): string
    {
        if ($converted = $this->convertUaRuSourcesIdsToUkIfNeeded([$sourceId])) {
            return reset($converted);
        }

        return $sourceId;
    }

    public function getFallbackRuSourceIdForUkSource($ukSourceId): ?string
    {
        if (($foundedKey = array_search($ukSourceId, self::MAPPING, true)) !== false) {
            return $foundedKey;
        }

        return null;
    }
}