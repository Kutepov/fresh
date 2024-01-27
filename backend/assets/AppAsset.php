<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Main backend application asset bundle.
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
		'js/nprogress/nprogress.css',
        'css/site.css',
    ];
    public $js = [
		'js/nprogress/nprogress.js',
        'js/site.js',
    ];
    public $depends = [
        TooltipsterAsset::class,
        'yii\web\YiiAsset',
        'yii\bootstrap\BootstrapAsset',
    ];
}
