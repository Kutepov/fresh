<?php namespace common\services;

use Carbon\Carbon;
use common\exceptions\AppCreationException;
use common\models\App;
use common\models\User;
use yii;

class AppsService
{
    private $transactionManager;
    /** @var App[]|array */
    private $apps;
    private const MUTEX_TIMEOUT = 6;

    public function __construct(DbManager $transactionManager)
    {
        $this->transactionManager = $transactionManager;
    }

    /**
     * Создание записи в бд о приложении при ее отсутствии, с учетом кластера бд и race conditions
     * @param string $platform
     * @param string $deviceId
     * @param string|null $version
     * @param ?string $country
     * @param ?string $language
     * @param string|null $articlesLanguage
     * @return App
     * @throws AppCreationException
     */
    public function findOrCreate(
        string  $platform,
        string  $deviceId,
        ?string $version = null,
        ?string $country = null,
        ?string $language = null,
        ?string $articlesLanguage = null,
        ?array  $sources = null,
        ?array  $categories = null,
        ?array  $sourcesUrls = null,
        ?string $articlesPreviewType = null
    ): App
    {
        $key = $this->getKey($platform, $deviceId);

        if (!isset($this->apps[$key])) {
            $app = $this->findByDevice($platform, $deviceId);

            if (!$app && Yii::$app->mutex->acquire($key, self::MUTEX_TIMEOUT)) {
                try {
                    $this->transactionManager->wrap(function () use ($platform, $deviceId, $version, $country, $language, $articlesLanguage, $sources, $categories, $articlesPreviewType) {
                        $model = new App([
                            'platform' => $platform,
                            'device_id' => $deviceId,
                            'version' => $version,
                            'country' => $country,
                            'language' => $language,
                            'articles_language' => $articlesLanguage,
                            'preview_type' => $articlesPreviewType
                        ]);

                        if (is_array($sources) && is_array($categories)) {
                            $model->enabled_sources = $sources;
                            $model->enabled_categories = $categories;
                        }

                        if (!$model->save()) {
                            throw new AppCreationException();
                        }

                        return $model;
                    });
                } catch (\Exception $e) {
                    if ($e->getCode() != 23000) {
                        throw $e;
                    }
                }

                Yii::$app->mutex->release($key);
            }

            if (is_null($app)) {
                $app = $this->findByDevice($platform, $deviceId);
            }

            if (is_null($app)) {
                throw new AppCreationException(['platform' => $platform, 'deviceId' => $deviceId]);
            }

            $app->setAttributes([
                'language' => $language,
                'articles_language' => $articlesLanguage,
                'country' => $country,
                'version' => $version,
                'preview_type' => $articlesPreviewType
            ]);

            if (is_array($sources) && is_array($categories)) {
                $sources = array_values($sources);
                $categories = array_values($categories);

                sort($sources);
                sort($categories);

                $app->setAttributes([
                    'enabled_sources' => $sources,
                    'enabled_categories' => $categories
                ]);
            }

            if (is_array($sourcesUrls)) {
                $sourcesUrls = array_values($sourcesUrls);

                sort($sourcesUrls);

                $app->setAttributes([
                    'enabled_sources_urls' => $sourcesUrls
                ]);

                if (!$app->sources_subscriptions_processed) {
                    $app->setAttributes(['sources_subscriptions_processed' => 1]);
                    $queueManager = Yii::$container->get(QueueManager::class);
                    $queueManager->createAppSourcesSubscriptionInitialRecalc($app, $sourcesUrls);
                }
            }

            if (!Yii::$app->user->isGuest && Yii::$app->user->identity instanceof User) {
                $app->user_id = Yii::$app->user->id;
            }

            if (
                $app->isAttributeChanged('user_id') ||
                $app->isAttributeChanged('language') ||
                $app->isAttributeChanged('articles_language') ||
                $app->isAttributeChanged('country') ||
                $app->isAttributeChanged('version') ||
                $app->isAttributeChanged('enabled_sources') ||
                $app->isAttributeChanged('enabled_sources_urls', false) ||
                $app->isAttributeChanged('enabled_categories') ||
                $app->isAttributeChanged('preview_type')
            ) {
                $app->updated_at = Carbon::now();
                $app->save(false);
            }

            $this->apps[$key] = $app;
        }

        return $this->apps[$key];
    }

    public function validateApp($platform, $deviceId): bool
    {
        if (!in_array($platform, [App::PLATFORM_IOS, App::PLATFORM_ANDROID, App::PLATFORM_WEB], true)) {
            return false;
        }

        switch ($platform) {
            case App::PLATFORM_ANDROID:
                return preg_match('#^[a-f\d]{16}$#i', $deviceId) ||
                    preg_match('#^[a-f\d]{8}-[a-f\d]{4}-[a-f\d]{4}-[a-f\d]{4}-[a-f\d]{12}$#i', $deviceId);

            case App::PLATFORM_IOS:
                return preg_match('#^[a-f\d]{8}-[a-f\d]{4}-[a-f\d]{4}-[a-f\d]{4}-[a-f\d]{12}$#i', $deviceId);

            case App::PLATFORM_WEB:
                return preg_match('#^[a-f0-9]{32}$#i', $deviceId);
        }

        return false;
    }

    private function findByDevice(string $platform, string $deviceId): ?App
    {
        return $this->transactionManager->wrap(function () use ($platform, $deviceId) {
            return App::find()->byDevice($platform, $deviceId)->one();
        });
    }

    private function getKey(string $platform, string $deviceId)
    {
        return 'app-' . $platform . '-' . $deviceId;
    }
}