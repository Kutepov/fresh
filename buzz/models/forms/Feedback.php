<?php namespace buzz\models\forms;

use yii\base\Model;

class Feedback extends Model
{
    public $name;
    public $email;
    public $message;

    public function rules()
    {
        return [
            [['name', 'email', 'message'], 'string'],
            [['name', 'email', 'message'], 'required'],
            ['email', 'email'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => \t('Имя'),
            'email' => \t('E-mail'),
            'message' => \t('Сообщение')
        ];
    }

    public function send()
    {
        return \Yii::$app->mailer
            ->compose('feedback', [
                'name' => $this->name,
                'message' => $this->message,
                'email' => $this->email
            ])
            ->setFrom(\Yii::$app->params['noreplyEmail'])
            ->setReplyTo($this->email)
            ->setTo(\Yii::$app->params['infoEmail'])
            ->setSubject('fresh.buzz (Обратная связь)')
            ->send();
    }
}