<?php namespace common\services;

use common\models\App;
use yii;

class QualitySurveyService
{
    public function good(App $app): void
    {
        $app->countryModel->updateCounters([
            'quality_survey_good' => 1
        ]);
    }

    public function bad(App $app): void
    {
        $app->countryModel->updateCounters([
            'quality_survey_bad' => 1
        ]);
    }

    public function feedback(App $app, ?string $message): void
    {
        $letter = Yii::$app->mailer
            ->compose('survey', [
                'name' => $app->user->name ?? null,
                'message' => $message,
                'country' => $app->countryModel->name ?? null
            ])
            ->setFrom(Yii::$app->params['noreplyEmail'])
            ->setTo(Yii::$app->params['infoEmail'])
            ->setSubject('myfresh.app (Опрос по качеству новостей)');

        if ($app->user) {
            $letter = $letter->setReplyTo($app->user->email);
        }
        $letter->send();
    }
}