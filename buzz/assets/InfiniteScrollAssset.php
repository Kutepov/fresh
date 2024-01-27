<?php namespace buzz\assets;

use yii\web\AssetBundle;

class InfiniteScrollAssset extends AssetBundle
{

    public $basePath = '@webroot';
    public $baseUrl = '@web';

    public $js = [
        '/js/infinite-ajax-scroll.min.js',
    ];
}