<?php namespace buzz\assets;

use yii\web\AssetBundle;
use yii\web\YiiAsset;

class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/app.min.css',
    ];
    public $js = [
        'js/app.min.js',
    ];
    public $depends = [
        YiiAsset::class
    ];
}