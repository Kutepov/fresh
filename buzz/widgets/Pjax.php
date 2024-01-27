<?php namespace buzz\widgets;


use yii\helpers\Json;
use yii\widgets\PjaxAsset;

class Pjax extends \yii\widgets\Pjax
{
    public $enablePushState = false;
    public $enableReplaceState = false;

    public function registerClientScript()
    {
        $id = $this->options['id'];
        $this->clientOptions['push'] = $this->enablePushState;
        $this->clientOptions['replace'] = $this->enableReplaceState;
        $this->clientOptions['timeout'] = $this->timeout;
        $this->clientOptions['scrollTo'] = $this->scrollTo;
        if (!isset($this->clientOptions['container'])) {
            $this->clientOptions['container'] = "#$id";
        }
        $options = Json::htmlEncode($this->clientOptions);
        $js = '';
        if ($this->linkSelector !== false) {
            $linkSelector = Json::htmlEncode($this->linkSelector !== null ? $this->linkSelector : '#' . $id . ' a');
            $js .= "jQuery(document).off('click.pjax', $linkSelector);";
            $js .= "jQuery(document).pjax($linkSelector, $options);";
        }
        if ($this->formSelector !== false) {
            $formSelector = Json::htmlEncode($this->formSelector !== null ? $this->formSelector : '#' . $id . ' form[data-pjax]');
            $submitEvent = Json::htmlEncode($this->submitEvent);
            $js .= "\njQuery(document).off($submitEvent, $formSelector);";
            $js .= "\njQuery(document).on($submitEvent, $formSelector, function (event) {jQuery.pjax.submit(event, $options);});";
        }
        $view = $this->getView();
        PjaxAsset::register($view);

        if ($js !== '') {
            $view->registerJs($js);
        }
    }
}