<?php namespace buzz\widgets;

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;

class ActiveField extends \yii\widgets\ActiveField
{
    public $template = '{label}<div class="error-notify">{input}{error}</div>';
    public $options = [
        'tag' => 'fieldset',
    ];
    public $errorOptions = ['tag' => 'span', 'class' => 'error-text'];
    public $labelOptions = ['class' => false];


    public function textInput($options = [])
    {
        $options = array_merge($options, !$options['class'] ? ['class' => 'textfield'] : []);

        return parent::textInput($options);
    }

    public function ratingInput($options = [])
    {
        $this->options['class'] = 'rating';
        $stars = '<ul data-select-rating class="rating-active rating-big">';

        for ($i = 1; $i < 6; $i++) {
            $stars .= '<li data-rate="' . $i . '" ' . ($this->model->{$this->attribute} == $i ? 'data-checked="1"' : '') . ' ' . ($this->model->{$this->attribute} >= $i ? 'class="rated"' : '') . '></li>';
        }
        $stars .= '</ul>';

        $options['class'] = 'rating';
        $this->parts['{input}'] = $stars;
        $this->parts['{input}'] .= Html::activeHiddenInput($this->model, $this->attribute, $options);

        return $this;
    }

    public function passwordInput($options = [])
    {
        $options = array_merge($options, ['class' => 'textfield']);

        parent::passwordInput($options);
        $this->parts['{input}'] .= Html::button('', ['type' => 'button', 'class' => 'pass-show']);
        $this->parts['{input}'] = Html::tag('div', $this->parts['{input}'], ['class' => 'pass-field']);

        return $this;

    }

    public function textarea($options = [])
    {
        $options =  ArrayHelper::merge(['class' => 'textarea'], $options);

        return parent::textarea($options);
    }

    public function dropDownList($items, $options = [])
    {
        $options = array_merge(['class' => 'select'], $options);
        return parent::dropDownList($items, $options);
    }

    public function tip($content, $position = 'bottom')
    {
        if (!$content) {
            return '';
        }
        $this->parts['{tip}'] = Html::tag('span', null, [
            'class' => 'qtip-icon to-' . $position,
            'title' => $content
        ]);

        return $this;
    }

    public function render($content = null)
    {
        if ($content === null) {
            if (!isset($this->parts['{input}'])) {
                $this->textInput();
            }
            if (!isset($this->parts['{label}'])) {
                $this->label();
            }
            if (!isset($this->parts['{error}'])) {
                $this->error();
            }
            if (!isset($this->parts['{hint}'])) {
                $this->hint(null);
            }
            if (!isset($this->parts['{tip}'])) {
                $this->tip(null);
            }
            $content = strtr($this->template, $this->parts);
        } elseif (!is_string($content)) {
            $content = call_user_func($content, $this);
        }

        return $this->begin() . "\n" . $content . "\n" . $this->end();
    }
}