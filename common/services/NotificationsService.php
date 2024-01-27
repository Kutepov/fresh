<?php namespace common\services;

use yii;
use common\models\User;

class NotificationsService
{
    public function sendUserSignUpConfirmNotify(User $user)
    {
        if ($user->status === User::STATUS_ACTIVE) {
            throw new yii\base\Exception('User was confirmed earlier.');
        }

        if (!$user->verification_token) {
            throw new yii\base\Exception('Email confirm token is empty');
        }

        return Yii::$app->mailer
            ->compose('users/sign-up-confirm', [
                'email' => $user->email,
                'code' => $user->verification_token
            ])
            ->setFrom(Yii::$app->params['noreplyEmail'])
            ->setTo($user->email)
            ->setSubject('[' . Yii::$app->name . '] ' . \t('Подтверждение регистрации'))
            ->send();
    }

    public function sendRestorePassConfirmCodeNotify(User $user)
    {
        if (!$user->verification_token) {
            throw new yii\base\Exception('Confirm code is empty.');
        }

        return Yii::$app->mailer
            ->compose('users/restore-password-confirm', [
                'email' => $user->email,
                'code' => $user->verification_token
            ])
            ->setFrom(Yii::$app->params['noreplyEmail'])
            ->setTo($user->email)
            ->setSubject('[' . Yii::$app->name . '] ' . \t('Восстановление пароля'))
            ->send();
    }
}