<?php declare(strict_types=1);

namespace common\services;

use common\models\Category;
use common\models\SourceUrl;

class CatalogService
{
    private $categories;
    private const TOP_CATEGORIES_LIMIT = 5;

    public function __construct(CategoriesService $categories)
    {
        $this->categories = $categories;
    }

    public function getCategoriesList(string $country, ?string $language, ?string $platform = null): array
    {
        $categories = $this->categories->getCategoriesList(
            $country,
            $language,
            $platform
        );

        /** Категория "Видео" в каталоге не нужна */
        $categories = array_filter($categories, function (Category $category) {
            return $category->name !== 'video';
        });

        foreach ($categories as $i => &$category) {
            $category->setScenario(Category::SCENARIO_CATALOG);
            if ($i + 1 <= self::TOP_CATEGORIES_LIMIT) {
                $category->top = true;
            }
        }

        return array_values($categories);
    }

    /**
     * @param string|array $type
     * @param string $country
     * @param string|null $language
     * @return array
     */
    public function getRecommendedSources($type, string $country, ?string $language): array
    {
        $query = SourceUrl::find()
            ->enabled()
            ->defaultOnly()
            ->byCountry($country)
            ->byLanguage($language)
            ->orderBy(['sources_urls.name' => SORT_ASC]);

        if ($type) {
            $query->byType($type);
        }

        return $query->all();
    }
}