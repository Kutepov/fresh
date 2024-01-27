<?php

namespace backend\assets;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;

class TooltipsterAsset extends AssetBundle
{
    public $js = [
        'js/tooltipster.bundle.min.js',
    ];

    public $css = [
        'css/tooltipster.bundle.min.css',
        'css/plugins/tooltipster/sideTip/themes/tooltipster-sideTip-shadow.min.css'
    ];

    public function init()
    {
        $this->sourcePath = __DIR__ . '/../../vendor/tooltipster/tooltipster/dist';
    }

    public $depends = [
        JqueryAsset::class
    ];
}
