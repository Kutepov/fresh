<?php namespace api\controllers;

use api\models\search\ArticlesSearch;
use api\models\search\ArticlesGroupedByCategorySearch;
use api\models\search\SameArticlesSearch;
use api\models\search\SimilarArticlesSearch;
use api\models\search\TopArticlesSearch;
use common\models\Article;
use common\services\ArticlesService;
use yii\base\UserException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Request;

class ArticlesController extends Controller
{
    /** @var ArticlesService */
    private $service;

    public function __construct($id, $module, ArticlesService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = ArrayHelper::merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'add' => ['post'],
                    'update' => ['post'],
                    'rating-up' => ['post'],
                    'rating-down' => ['post'],
                ],
            ],
        ]);

        $behaviors['authenticator']['optional'] = [
            'index',
            'index-by-category',
            'index-deprecated',
            'top',
            'top-deprecated',
            'newest',
            'same',
            'same-amount',
            'body',
            'bodies',
            'similar',
            'check-country',
            'by-ids',
            'by-slug',
            'rating-up',
            'rating-down',
            'new-amount'
        ];

        return $behaviors;
    }

    /**
     * Список кратких новостей
     * @param Request $request
     * @return array
     */
    public function actionIndex(Request $request): array
    {
        $searchForm = new ArticlesSearch(ArticlesSearch::SCENARIO_FIND);

        return $this->service->search(
            $searchForm->loadAndValidate($request->get())
        );
    }

    /**
     * Список кратких новостей
     * @param Request $request
     * @return array
     * @deprecated
     */
    public function actionIndexByCategory(Request $request): array
    {
        $searchForm = new ArticlesSearch(ArticlesSearch::SCENARIO_FIND_BY_CATEGORY_NAME);

        return $this->service->search(
            $searchForm->loadAndValidate($request->get())
        );
    }

    /**
     * Список кратких новостей
     * @param Request $request
     * @return array
     * @deprecated
     */
    public function actionIndexDeprecated(Request $request): array
    {
        $searchForm = new ArticlesSearch(ArticlesSearch::SCENARIO_DEPRECATED_FIND);

        return $this->service->search(
            $searchForm->loadAndValidate($request->get())
        );
    }

    public function actionNewAmount(Request $request): int
    {
        $searchForm = new ArticlesSearch(ArticlesSearch::SCENARIO_NEW_ARTICLES_AMOUNT);

        return $this->service->getNewArticlesAmount(
            $searchForm->loadAndValidate($request->get())
        );
    }

    /**
     * Топ новостей
     * @param Request $request
     * @return array
     */
    public function actionTop(Request $request): array
    {
        $searchForm = new TopArticlesSearch();

        return $this->service->getTopArticles(
            $searchForm->loadAndValidate($request->get())
        );
    }

    /**
     * Топ новостей
     * @param Request $request
     * @return Article[]
     * @deprecated
     */
    public function actionTopDeprecated(Request $request): array
    {
        $searchForm = new TopArticlesSearch(TopArticlesSearch::SCENARIO_DEPRECATED);

        return $this->service->getTopArticles(
            $searchForm->loadAndValidate($request->get())
        );
    }

    /**
     * Список новых новостей по категориям
     * @param Request $request
     * @return array
     * @deprecated
     */
    public function actionNewest(Request $request): array
    {
        $searchForm = new ArticlesGroupedByCategorySearch();

        return $this->service->getNewestArticlesGroupedByCategory(
            $searchForm->loadAndValidate($request->get())
        );
    }

    /**
     * Список новостей из разных источников, аналогичных ID переданной
     * @param Request $request
     * @return array
     * @throws BadRequestHttpException
     */
    public function actionSame(Request $request): array
    {
        $searchForm = new SameArticlesSearch();
        $searchForm->loadAndValidate($request->get());

        if ($searchForm->hasErrors()) {
            throw new BadRequestHttpException();
        }

        return $this->service->searchSameArticles($searchForm);
    }

    /**
     * Количество одинаковых новостей из разных источников
     * @return array
     * @deprecated
     */
    public function actionSameAmount(Request $request): array
    {
        $get = $request->get();
        $get['parentArticlesIds'] = $get['ids'];
        unset ($get['ids']);

        $searchForm = new SameArticlesSearch(SameArticlesSearch::SCENARIO_BULK);
        $searchForm->loadAndValidate($get);

        if ($searchForm->hasErrors()) {
            throw new BadRequestHttpException();
        }

        return $this->service->getSameArticlesAmount($searchForm);
    }

    /**
     * Тело полной новости по ID
     * @param Request $request
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionBody(Request $request): string
    {
        if (!($article = Article::findById($request->get('articleId')))) {
            throw new NotFoundHttpException();
        }

        return $this->service->getEncodedBody($article);
    }

    /**
     * Список полных новостей по их ID
     * @param Request $request
     * @return array
     */
    public function actionBodies(Request $request): array
    {
        $ids = $request->get('ids', []);
        $articles = Article::findByIds($ids);

        return $this->service->getEncodedBodies($articles);
    }

    public function actionByIds(Request $request): array
    {
        $searchForm = new ArticlesSearch(ArticlesSearch::SCENARIO_FIND);

        return $this->service->getByIds(
            $searchForm->loadAndValidate($request->get())
        );
    }

    /**
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionBySlug($slug = null): ?Article
    {
        if ($article = $this->service->getBySlug(
            $slug
        )) {
            return $article;
        }

        throw new NotFoundHttpException();
    }

    public function actionSimilar(Request $request): array
    {
        $searchForm = new SimilarArticlesSearch();
        $searchForm->loadAndValidate($request->get());

        if ($searchForm->hasErrors()) {
            return [];
        }

        return $this->service->searchSimilarArticles($searchForm);
    }

    /**
     * Проверка наличия новостей для заданной страны
     * @param Request $request
     * @return bool
     */
    public function actionCheckCountry(Request $request): bool
    {
        if ($countryCode = $request->get('country')) {
            return Article::find()->bySource(
                null,
                $countryCode,
                $request->get('articlesLanguage')
            )->exists();
        }

        return false;
    }

    /**
     * Повышение рейтинга новости
     * @param \yii\web\Request $request
     * @return int
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRatingUp(Request $request): int
    {
        try {
            return $this->service->increaseRating(
                $request->get('id'),
                $this->currentApp
            );
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * Понижение рейтинга новости
     * @param \yii\web\Request $request
     * @return int
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRatingDown(Request $request): int
    {
        try {
            return $this->service->decreaseRating(
                $request->get('id'),
                $this->currentApp
            );
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}