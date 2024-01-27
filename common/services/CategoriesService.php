<?php namespace common\services;

use Carbon\Carbon;
use common\components\caching\Cache;
use common\models\App;
use common\models\Article;
use common\models\Category;
use common\models\CategoryCountry;
use common\models\FolderCountry;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;

class CategoriesService
{
    private $multilingualService;

    public function __construct(MultilingualService $multilingualService)
    {
        $this->multilingualService = $multilingualService;
    }

    public function calcArticlesInCategories(?Carbon $fromDate = null): void
    {
        if (is_null($fromDate)) {
            $fromDate = Carbon::parse('14 days ago');
        }

        /** @var CategoryCountry[] $categoriesForCountries */
        $categoriesForCountries = CategoryCountry::find()->all();

        foreach ($categoriesForCountries as $categoryForCountry) {
            $articlesExists = Article::find()
                    ->byCategory($categoryForCountry->category_id)
                    ->byCountry( $categoryForCountry->country)
                    ->createdAt($fromDate->startOfDay(), Carbon::now())
                    ->exists();

            $categoryForCountry->updateAttributes([
                'articles_exists' => $articlesExists
            ]);

            /** Если у категории есть связанная папка - обновляем и для нее */
            if ($folder = $categoryForCountry->category->folder) {
                FolderCountry::updateAll([
                    'articles_exists' => $articlesExists
                ], [
                    'folder_id' => $folder->id,
                    'country' => $categoryForCountry->country
                ]);
            }

            if ($categoryForCountry->isAttributeChanged('articles_exists', false)) {
                Category::updateAll([
                    'updated_at' => Carbon::now()->toDateTimeString()
                ], [
                    'id' => $categoryForCountry->category_id
                ]);
            }
        }
    }

    /**
     * @return Category[]
     */
    public function getCategoriesList(?string $country, ?string $language, ?string $platform = null): array
    {
        return $this->getCategoriesQuery($country, $language, $platform)->all();
    }

    public function getCategoryBySlug(string $slug, ?string $country = null, ?string $language = null, ?string $platform = null): ?Category
    {
        return $this
            ->getCategoriesQuery($country, $language, $platform)
            ->andWhere(['categories.name' => $slug])
            ->one();
    }

    private function getCategoriesQuery(?string $country, ?string $language, ?string $platform): ActiveQuery
    {
        $query = Category::find();

        if ($country) {
            $query->forCountry($country);
        }

        if ($platform) {
            $query = $query->forPlatform($platform);

            if ($platform === App::PLATFORM_IOS) {
                $query->andFilterWhere([
                    '<>', 'categories.id', 'b72d1e8f-503e-4cda-94d2-f5779af32634'
                ]);
            }
        }

        if (empty($language) || !$this->multilingualService->isSupportedLanguage($language)) {
            $language = $this->multilingualService->getLanguageCodeForCountry($country);
        }

        return $query->forLanguage($language)
            ->orderByPriority()
            ->cache(
                Cache::DURATION_CATEGORIES_LIST,
                new TagDependency(['tags' => Cache::TAG_CATEGORIES_LIST])
            );
    }
}