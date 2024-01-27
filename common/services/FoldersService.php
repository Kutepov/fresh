<?php namespace common\services;

use common\models\Category;
use common\models\CategoryCountry;
use common\models\Folder;
use common\models\FolderCountry;
use common\models\SourceUrl;
use common\queries\Source;
use yii\db\ActiveQuery;
use yii\db\Query;

class FoldersService
{
    private $multilingualService;

    public function __construct(MultilingualService $multilingualService)
    {
        $this->multilingualService = $multilingualService;
    }

    public function createOrUpdateFolderForCategory(Category $category): Folder
    {
        $folder = $category->folder;

        if (!$folder) {
            $folder = new Folder([
                'category_id' => $category->id,
                'priority' => $category->isDefault ? -1 : $category->priority
            ]);

            if (!$folder->save()) {
                throw new \RuntimeException('Could not save folder');
            }

            /** Категории источников */
            SourceUrl::updateAll([
                'folder_id' => $folder->id
            ], [
                'category_id' => $category->id
            ]);
        } else {
            $folder->updateAttributes(['priority' => $category->priority]);
        }

        /** Переводы */
        $langs = (new Query())->from('categories_lang')
            ->where(['owner_id' => $category->id])
            ->select('*')
            ->all();

        foreach ($langs as $lang) {
            (new Query())->createCommand()
                ->insertIgnore('folders_lang', [
                    'owner_id' => $folder->id,
                    'language' => $lang['language'],
                    'title' => $lang['title']
                ], ['title'])
                ->execute();

            /** Доступность в странах */
            $categoriesCountries = CategoryCountry::find()
                ->where(['category_id' => $category->id])
                ->all();

            $actualCountries = [];
            foreach ($categoriesCountries as $categoryCountry) {
                $actualCountries[] = $categoryCountry->country;
                (new FolderCountry([
                    'folder_id' => $folder->id,
                    'country' => $categoryCountry->country,
                    'articles_exists' => $categoryCountry->articles_exists
                ]))->save();
            }

            if (count($actualCountries)) {
                FolderCountry::deleteAll([
                    'AND',
                    ['NOT IN', 'country', $actualCountries],
                    ['=', 'folder_id', $folder->id]
                ]);
            }
        }

        return $folder;
    }

    public function getFoldersList(string $country, ?string $language, ?string $articlesLanguage, ?string $platform = null): array
    {
        return $this->getFoldersQuery($country, $language, $articlesLanguage, $platform)->all();
    }

    private function getFoldersQuery(string $country, ?string $language, ?string $articlesLanguage, ?string $platform): ActiveQuery
    {
        $query = Folder::find()->forCountry($country);

        if ($platform) {
            $query = $query->forPlatform($platform);
        }

        if (empty($language) || !$this->multilingualService->isSupportedLanguage($language)) {
            $language = $this->multilingualService->getLanguageCodeForCountry($country);
        }

        $query->innerJoinWith([
            'sourcesUrls' => static function (\common\queries\SourceUrl $query) use ($country, $articlesLanguage) {
                $query->enabled()
                    ->defaultOnly()
                    ->byCountry($country)
                    ->innerJoinWith([
                        'source' => static function (Source $query) use ($country, $articlesLanguage) {
                            $query->enabled()
                                ->defaultOnly()
                                ->byLanguage($articlesLanguage);
                        }
                    ]);
            }
        ]);

        return $query->forLanguage($language)
            ->orderByPriority();
    }
}