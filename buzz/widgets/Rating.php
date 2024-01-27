<?php namespace buzz\widgets;

use common\models\Article;
use common\models\Comment;
use yii\base\UserException;
use yii\base\Widget;
use yii\helpers\Url;

class Rating extends Widget
{
    /** @var \common\contracts\RateableEntity */
    public $entity;

    /**
     * @throws \yii\base\UserException
     */
    public function run()
    {
        if ($this->entity instanceof Article) {
            $entityType = 'article';
        }
        elseif ($this->entity instanceof Comment) {
             $entityType = 'comment';
        }
        else {
            throw new UserException('Unsupported rateable entity');
        }

        return $this->render('rating/widget', [
            'entity' => $this->entity,
            'entityType' => $entityType
        ]);
    }
}