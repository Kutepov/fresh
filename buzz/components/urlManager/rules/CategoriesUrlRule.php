<?php namespace buzz\components\urlManager\rules;

use common\components\helpers\SEO;
use common\models\Category;
use common\services\CategoriesService;
use Yii;
use yii\base\BaseObject;
use yii\web\UrlRuleInterface;
use yii2mod\enum\helpers\BaseEnum;

class CategoriesUrlRule extends BaseObject implements UrlRuleInterface
{
    /** @var CategoriesService */
    private $service;

    public function init()
    {
        $this->service = Yii::$container->get(CategoriesService::class);
        parent::init();
    }

    public function createUrl($manager, $route, $params)
    {
        if ($route === 'articles/index' && isset($params['categoryName'])) {
            $url = $params['categoryName'];
            unset($params['categoryName']);
            if (count($params)) {
                $url .= '?' . http_build_query($params);
            }

            return $url;
        }

        return false;
    }

    /**
     * @param \buzz\components\urlManager\urlManager $manager
     * @param \yii\web\Request $request
     * @return array|false
     * @throws \yii\base\InvalidConfigException
     */
    public function parseRequest($manager, $request)
    {
        $currentPath = trim($request->url, '/');
        $langPattern = implode('|', $manager->languages);
        $language = null;

        if (preg_match('#^(' . $langPattern . ')(/|$)#i', $currentPath, $m)) {
            [$language, $country] = explode('-', $m[1]);
            if (!$country) {
                $country = $language;
            }
        } else {
           $country = Yii::$app->urlManager->getDefaultCountry();
        }

        if (preg_match('#^(?:(?:' . $langPattern . ')/|)([\w-]+)(/|$|\?)#i', $currentPath, $m)) {
            if (!preg_match('#^(' . $langPattern . ')$#i', $m[1])) {
                if ($m[1] === Category::USER_SOURCE_SLUG) {
                    SEO::noIndexNofollow();
                }
                if ($category = $this->service->getCategoryBySlug($m[1], $country, $language)) {
                    $params['categoryName'] = $category->name;
                    return ['articles/index', $params];
                }
            }
        }

        return false;
    }
}