<?php declare(strict_types=1);

namespace common\services;

use buzz\components\urlManager\urlManager;
use common\models\Article;
use samdark\sitemap\Index;
use samdark\sitemap\Sitemap;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

class SitemapService
{
    private CategoriesService $categories;
    private MultilingualService $multilingual;
    private DbManager $dbManager;
    private array $hrefLangs;

    public function __construct(CategoriesService $categories, MultilingualService $multilingual, DbManager $dbManager)
    {
        $this->dbManager = $dbManager;
        $this->categories = $categories;
        $this->multilingual = $multilingual;

        $this->hrefLangs = $this->multilingual->getHrefLangs();
    }

    public function generate(bool $articles = true)
    {
        $sitemaps = $this->addCategories();

        if ($articles) {
            $sitemaps = ArrayHelper::merge($sitemaps, $this->addArticles());
        }

        $index = new Index(\Yii::getAlias('@buzz/web/uploads/sitemap/index.xml'));
        foreach ($sitemaps as $sitemap) {
            $index->addSitemap($sitemap);
        }
        $index->write();
    }

    private function addCategories(): array
    {
        $sitemap = new Sitemap(\Yii::getAlias('@buzz/web/uploads/sitemap/categories.xml'), true);
        $sitemap->setMaxBytes(52428800);

        $websiteLanguages = $this->multilingual->getAvailableWebsiteLanguagesForUrlManager();
        $urls = [];

        foreach ($websiteLanguages as $language) {
            $hrefLang = $this->hrefLangs[$language];
            if (!$hrefLang) {
                continue;
            }
            $urls[$hrefLang] = $this->generateUrlFromRoute(
                ['articles/index'],
                $language
            );
        }
        $sitemap->addItem($urls);

        $categories = $this->categories->getCategoriesList(null, null);

        foreach ($categories as $category) {
            $urls = [];
            foreach ($category->countries as $country) {
                foreach ($country->countryModel->urlLanguages as $urlLanguage) {
                    $hrefLang = $this->hrefLangs[$urlLanguage];
                    if (!$hrefLang) {
                        continue;
                    }
                    $urls[$hrefLang] = $this->generateUrlFromRoute(
                        $category->route,
                        $urlLanguage
                    );
                }
            }
            $sitemap->addItem($urls);
        }
        $sitemap->write();

        return $sitemap->getSitemapUrls(\Yii::$app->urlManager->hostInfo . '/uploads/sitemap/');
    }

    private function addArticles(): array
    {
        $sitemap = new Sitemap(\Yii::getAlias('@buzz/web/uploads/sitemap/articles.xml'), true);
        $sitemap->setMaxBytes(52428800);
        $unbufferedConnection = $this->dbManager->getUnbufferedConnection();

        $articlesBatch = Article::find()
            ->newestFirst()
            ->defaultOnly()
            ->batch(1000, $unbufferedConnection);

        /** @var Article[] $articles */
        foreach ($articlesBatch as $articles) {
            /** @var Article $article */
            foreach ($articles as $article) {
                $hrefLang = $this->hrefLangs[$article->urlLanguage];
                if (!$hrefLang) {
                    continue;
                }

                $sitemap->addItem([
                    $hrefLang => $article->sharingUrl
                ]);
            }
        }

        $sitemap->write();
        return $sitemap->getSitemapUrls(\Yii::$app->urlManager->hostInfo . '/uploads/sitemap/');
    }

    private function generateUrlFromRoute(array $route, string $urlLanguage): string
    {
        /** @var urlManager $urlManager */
        $urlManager = \Yii::$app->urlManager;
        $route[$urlManager->languageParam] = $urlLanguage;

        return rtrim(Url::to($route, true), '/');
    }
}