<?php namespace common\components\helpers;

use yii;

class SEO
{
    public static function noIndexNofollow()
    {
        Yii::$app->view->params['noIndexNoFollow'] = true;
    }

    public static function noIndexFollow($robots = false)
    {
        if ($robots) {
            Yii::$app->view->params['robots'] = $robots;
        }

        Yii::$app->view->params['noIndexFollow'] = true;
    }

    public static function indexFollow()
    {
        if (!array_filter([Yii::$app->view->params['noIndexFollow'], Yii::$app->view->params['noIndexNoFollow']])) {
            Yii::$app->view->params['indexFollow'] = true;
        }
    }

    public static function getMetaDesc($tpl, $params = [])
    {
        $metaDescription = Yii::t('app/meta', 'desc-' . $tpl, $params);

        if (mb_strlen($metaDescription) > 150) {
            $metaDescription = mb_substr($metaDescription, 0, 150) . '...';
        }

        return $metaDescription;
    }

    public static function metaTitleTpl($tpl, $params = [], $prefix = '')
    {
        self::metaTitle($prefix . Yii::t('app/meta', 'title-' . $tpl, $params));
    }

    public static function metaDescriptionTpl($tpl, $params = [], $prefix = '')
    {
        self::metaDescription($prefix . self::getMetaDesc($tpl, $params));
    }

    public static function metaTagsTpl($tpl, $params = [], $prefix = '')
    {
        self::metaTitleTpl($tpl, $params, $prefix);
        self::metaDescriptionTpl($tpl, $params, $prefix);
    }

    public static function h1Tpl($tpl, $params = [])
    {
        return Yii::t('app/meta', 'h1-' . $tpl, $params);
    }

    public static function alt($tpl, $params)
    {
        return hEncode(Yii::t('app/meta', 'img-alt-' . $tpl, $params));
    }

    public static function title($tpl, $params)
    {
        return hEncode(Yii::t('app/meta', 'img-title-' . $tpl, $params));
    }

    public static function metaTitle($title)
    {
        Yii::$app->view->title = mb_ucfirst($title);
    }

    public static function metaDescription($content)
    {
        Yii::$app->view->registerMetaTag([
            'name' => 'description',
            'content' => mb_ucfirst(hEncode($content))
        ]);
    }
}