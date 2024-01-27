<?php

namespace frontend\models\forms;

use yii\base\Model;
use Yii;
use manchenkov\yii\recaptcha\ReCaptchaValidator;

class Feedback extends Model
{
    /** @var string */
    public $name;

    /** @var string */
    public $email;

    /** @var string */
    public $message;

    public $captcha;

    public function rules()
    {
        return [
            [['name', 'email', 'message'], 'string'],
            [['name', 'email', 'message'], 'required', 'message' => Yii::t('app', 'The field cannot be empty')],
            [['email'], 'email', 'message' => Yii::t('app', 'E-mail is not a valid email address')],
            ['captcha', ReCaptchaValidator::class, 'score' => 0.8, 'action' => 'feedback'],
        ];
    }

    /**
     * @return array
     */
    public function attributeLabels()
    {
        return [
            'name' => Yii::t('app', 'Name'),
            'email' => Yii::t('app', 'E-mail'),
            'message' => Yii::t('app', 'Message'),
        ];
    }

    /**
     * @return bool
     */
    public function notify()
    {
        return Yii::$app->mailer
            ->compose('feedback', [
                'name' => $this->name,
                'message' => $this->message,
                'email' => $this->email
            ])
            ->setFrom(Yii::$app->params['noreplyEmail'])
            ->setReplyTo($this->email)
            ->setTo(Yii::$app->params['infoEmail'])
            ->setSubject('myfresh.app (Обратная связь)')
            ->send();
    }
}