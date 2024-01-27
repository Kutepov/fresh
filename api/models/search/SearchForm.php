<?php namespace api\models\search;

use yii\base\Model;

abstract class SearchForm extends Model
{
    public function __construct(?string $scenario = null)
    {
        parent::__construct(['scenario' => $scenario ?: self::SCENARIO_DEFAULT]);
    }

    public function loadAndValidate(array $params): self
    {
        $this->setAttributes($params);
        $this->validate();

        return $this;
    }

    public function afterValidate()
    {
        /** Очищаем ошибочные значения */
        foreach ($this->attributes() as $attribute) {
            if ($this->hasErrors($attribute)) {
                $this->$attribute = null;
            }
        }

        parent::afterValidate();
    }

    public function formName()
    {
        return '';
    }
}