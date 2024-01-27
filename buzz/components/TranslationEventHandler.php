<?php namespace buzz\components;

use yii\i18n\MissingTranslationEvent;

class TranslationEventHandler
{
    public static function handleMissingTranslation(MissingTranslationEvent $event)
    {
        if (!preg_match('#^en-?#i', $event->language)) {
            $event->translatedMessage = \Yii::t($event->category, $event->message, [], 'en');
        }
    }
}