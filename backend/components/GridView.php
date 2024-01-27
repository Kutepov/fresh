<?php namespace backend\components;

use yii\helpers\Url;
use himiklab\sortablegrid\SortableGridAsset;

class GridView extends \kartik\grid\GridView
{
    public $sortableAction = ['sort'];

    public function init()
    {
        parent::init();
        $this->sortableAction = Url::to($this->sortableAction);
    }

    public function run()
    {
        $this->registerWidget();
        parent::run();
    }

    protected function registerWidget()
    {
        $view = $this->getView();
        $view->registerJs("jQuery('#{$this->options['id']}').SortableGridView('{$this->sortableAction}');");
        SortableGridAsset::register($view);
    }
}