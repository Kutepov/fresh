<?php

namespace frontend\widgets;

use yii\base\Widget;
use frontend\models\forms\Feedback as FeedbackForm;

class Feedback extends Widget
{
    /** @var FeedbackForm */
    private $model;

    public function init()
    {
        $this->model = new FeedbackForm();
        parent::init();
    }

    /**
     * @return string
     */
    public function run()
    {
        return $this->render('feedback', [
            'model' => $this->model,
        ]);
    }

}