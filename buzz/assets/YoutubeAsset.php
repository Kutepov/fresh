<?php namespace buzz\assets;

use yii\web\AssetBundle;

class YoutubeAsset extends AssetBundle
{
    public $js = [
        'https://www.youtube.com/iframe_api',
    ];
    public $depends = [
        AppAsset::class
    ];
}