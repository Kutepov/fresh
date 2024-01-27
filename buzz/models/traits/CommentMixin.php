<?php namespace buzz\models\traits;

/**
 * @see \common\models\Comment
 */
trait CommentMixin
{
    public function getPublicationDateLabel(): string
    {
        $createdAt = $this->created_at->locale(\Yii::$app->language)->setTimezone(CURRENT_TIMEZONE);
        $prefix = null;
        if ($this->created_at->isToday()) {
            $format = 'LT';
        }
        elseif ($this->created_at->isYesterday()) {
            $prefix = \t('вчера');
            $format = 'LT';
        }
        else {
            $format = 'L, LT';
        }

        return ($prefix ? $prefix . ', ' : '') . $createdAt->isoFormat($format);
    }
}