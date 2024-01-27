<?php namespace buzz\controllers;

use api\models\search\ArticlesSearch;
use api\models\search\CommentSearch;
use api\models\search\SimilarArticlesSearch;
use common\components\helpers\SEO;
use common\models\Category;
use common\services\ArticlesService;
use common\services\CategoriesService;
use common\services\CommentsService;
use yii\base\UserException;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Request;

class ArticlesController extends Controller
{
    private ArticlesService $articles;
    private CommentsService $comments;

    private CategoriesService $categories;

    public function __construct(
        $id,
        $module,
        ArticlesService $service,
        CommentsService $comments,
        CategoriesService $categories,
        $config = []
    )
    {
        $this->articles = $service;
        $this->comments = $comments;
        $this->categories = $categories;
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(Request $request, $categoryName = null)
    {
        if (preg_match('#^/[a-z]{2}-[a-z]{2}/articles(\?|$|/)#', $request->url)) {
            return $this->redirect(['articles/index'], 301);
        }

        $searchForm = new ArticlesSearch(
            $categoryName ? ArticlesSearch::SCENARIO_FIND_BY_CATEGORY_NAME : ArticlesSearch::SCENARIO_FIND
        );

        if (
            $categoryName &&
            $category = $this->categories->getCategoryBySlug($categoryName, null, CURRENT_LANGUAGE)
        ) {
            $this->view->params['languages'] = $category->urlLanguages;
            $h1 = $category->title;
        }

        if ($request->get('page') || $request->get('createdBefore')) {
            SEO::noIndexNofollow();
        }

        $searchForm = $searchForm->loadAndValidate(ArrayHelper::merge($request->get(), [
            'country' => CURRENT_COUNTRY,
            'articlesLanguage' => CURRENT_ARTICLES_LANGUAGE,
            'offset' => (abs((int)$request->get('page')) - 1) * 20,
            'limit' => 20,
            'skipBanned' => false
        ]));

        $articles = $this->articles->search($searchForm);

        return $this->render('list', [
            'articles' => $articles,
            'category' => $searchForm->getCategoryModel(),
            'country' => $searchForm->getCountryModel(),
            'h1' => $h1 ?? null
        ]);
    }

    /**
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionView($categorySlug, $id = null, $slug = null)
    {
        if (($slug && ($article = $this->articles->getBySlug($slug))) || ($id && ($article = $this->articles->getById($id)))) {
            if ($categorySlug !== Category::USER_SOURCE_SLUG && $article->category_name !== $categorySlug) {
                throw new NotFoundHttpException();
            }

            if (in_array($article->source->country, ['RU', 'BY'])) {
                throw new NotFoundHttpException();
            }

            if (
                ($article->source->countryModel->articlesLanguages && CURRENT_LANGUAGE !== $article->source->language) ||
                ($article->source->country && CURRENT_COUNTRY !== $article->source->country) ||
                (!$article->source->default && $categorySlug !== Category::USER_SOURCE_SLUG)
            ) {
                return $this->redirect($article->sharingUrl, 301);
            }

            if (!$article->source->default) {
                SEO::noIndexNofollow();
            }

            $commentsSearchForm = new CommentSearch(CommentSearch::SCENARIO_ARTICLE_TOP);
            $commentsSearchForm->articleId = $article->id;
            $comments = $this->comments->getList($commentsSearchForm);

            $similarArticlesSearchForm = new SimilarArticlesSearch();
            $similarArticlesSearchForm->setAttributes([
                'articlesLanguage' => CURRENT_ARTICLES_LANGUAGE,
                'country' => CURRENT_COUNTRY,
                'limit' => 3,
                'articleId' => $article->id,
                'skipBanned' => false
            ]);
            $similarArticles = $this->articles->searchSimilarArticles($similarArticlesSearchForm);

            $previousArticlesSearchForm = new ArticlesSearch(ArticlesSearch::SCENARIO_FIND);
            $previousArticlesSearchForm->setAttributes([
                'articlesLanguage' => CURRENT_ARTICLES_LANGUAGE,
                'country' => CURRENT_COUNTRY,
                'limit' => 7,
                'skipBanned' => false,
                'createdBefore' => $article->created_at,
                'category' => [$article->category_id]
            ]);
            $previousArticles = $this->articles->search($previousArticlesSearchForm);

            return $this->render('view', [
                'article' => $article,
                'comments' => $comments,
                'similarArticles' => ArrayHelper::merge($similarArticles, $previousArticles)
            ]);
        }

        throw new NotFoundHttpException();
    }

    /**
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRatingUp(Request $request): int
    {
        try {
            return $this->articles->increaseRating(
                $request->get('id'),
                $this->currentUser
            );
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRatingDown(Request $request): int
    {
        try {
            return $this->articles->decreaseRating(
                $request->get('id'),
                $this->currentUser
            );
        } catch (UserException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}