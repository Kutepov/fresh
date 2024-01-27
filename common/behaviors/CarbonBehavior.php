<?php namespace common\behaviors;

use Carbon\Carbon;

class CarbonBehavior extends \yii2mod\behaviors\CarbonBehavior
{
    /**
     * Convert the model's attributes to an Carbon instance.
     *
     * @param $event
     *
     * @return \yii2mod\behaviors\CarbonBehavior
     */
    public function attributesToCarbon($event)
    {
        foreach ($this->attributes as $attribute) {
            $value = $this->owner->$attribute;
            if (!empty($value)) {
                try {
                    // If this value is an integer, we will assume it is a UNIX timestamp's value
                    // and format a Carbon object from this timestamp.
                    if (is_numeric($value)) {
                        $this->owner->$attribute = Carbon::createFromTimestamp($value, 'UTC');
                    }

                    // If the value is in simply year, month, day format, we will instantiate the
                    // Carbon instances from that format.
                    elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
                        $this->owner->$attribute = Carbon::createFromFormat('Y-m-d', $value, 'UTC')->startOfDay();
                    }
                    else {
                        $this->owner->$attribute = Carbon::createFromFormat($this->dateFormat, $this->owner->$attribute, 'UTC');
                    }
                } catch (\Throwable $e) {
                    $this->owner->{$attribute} = null;
                    \Yii::error([
                        'attribute' => $attribute,
                        'value' => $value,
                        'class' => get_class($this->owner),
                        'id' => $this->owner->id,
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }
    }
}