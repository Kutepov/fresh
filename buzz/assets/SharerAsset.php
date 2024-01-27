<?php namespace buzz\assets;

use yii\web\AssetBundle;

class SharerAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';

    public $js = [
        '/js/sharer.min.js',
    ];
}