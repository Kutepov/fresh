<?php namespace buzz\widgets;

use buzz\controllers\ArticlesController;
use buzz\controllers\SiteController;
use common\models\Category;
use common\services\CategoriesService;
use yii\base\Widget;
use yii\helpers\Html;
use yii;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;

class HrefLang extends Widget
{
    public function run()
    {
        if (Yii::$app->request->isAjax) {
            return '';
        }

        if (Yii::$app->controller instanceof SiteController && Yii::$app->controller->action->id === 'error') {
            return '';
        }

        $result = "\n";

        if (Yii::$app->controller instanceof ArticlesController && Yii::$app->controller->action->id === 'view') {
            $result .= Html::tag(
                    'link',
                    '',
                    [
                        'rel' => 'alternate',
                        'hreflang' => str_replace('_', '-', Yii::$app->language),
                        'href' => Url::canonical()
                    ]
                ) . "\n";
                $result .= Html::tag(
                        'link',
                        '',
                        [
                            'rel' => 'alternate',
                            'hreflang' => 'x-default',
                            'href' => Url::canonical()
                        ]
                    ) . "\n";
        } else {
            if ($languages = Yii::$app->view->params['languages']) {

                foreach ($languages as $language) {
                    if ($hrefLang = Yii::$app->urlManager->hrefLangs[$language]) {
                        $result .= Html::tag(
                                'link',
                                '',
                                [
                                    'rel' => 'alternate',
                                    'hreflang' => $hrefLang,
                                    'href' => $this->route($language),
                                ]
                            ) . "\n";
                    }
                }
            } else {
                foreach (Yii::$app->urlManager->languages as $language) {
                    if ($hrefLang = Yii::$app->urlManager->hrefLangs[$language]) {
                        $result .= Html::tag(
                                'link',
                                '',
                                [
                                    'rel' => 'alternate',
                                    'hreflang' => $hrefLang,
                                    'href' => $this->route($language),
                                ]
                            ) . "\n";
                    }
                }

                $result .= Html::tag(
                        'link',
                        '',
                        [
                            'rel' => 'alternate',
                            'hreflang' => 'x-default',
                            'href' => $this->route('us'),
                        ]
                    ) . "\n";
            }
        }

        return $result;
    }

    public function route($lang)
    {
        $params = ArrayHelper::merge(
            ['/' . trim(Yii::$app->requestedRoute, '/')],
            Yii::$app->request->getQueryParams(),
            [
                Yii::$app->urlManager->languageParam => $lang
            ]
        );

        unset($params['createdBefore']);

        return rtrim(Url::to($params, true), '/');
    }
}