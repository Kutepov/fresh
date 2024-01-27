<?php namespace common\forms\users;

use common\models\User;
use yii\base\Model;
use yii\base\UserException;

class ChangePasswordForm extends Model
{
    public $email;
    public $currentPassword;
    public $password;
    public $repeatPassword;

    public $code;

    public const SCENARIO_RESTORE_PASSWORD = 'restorePassword';
    public const SCENARIO_SET_PASSWORD = 'setPassword';

    public function scenarios(): array
    {
        return [
            self::SCENARIO_DEFAULT => ['currentPassword', 'password', 'repeatPassword'],
            self::SCENARIO_SET_PASSWORD => ['password', 'repeatPassword'],
            self::SCENARIO_RESTORE_PASSWORD => ['password', 'repeatPassword', 'email', 'code']
        ];
    }

    public function rules(): array
    {
        return [
            [['currentPassword', 'password', 'repeatPassword'], 'required'],
            [['currentPassword', 'password', 'repeatPassword'], 'string', 'min' => 6],
            ['password', 'compare', 'compareAttribute' => 'repeatPassword', 'message' => \t('Пароли не совпадают')],
            ['currentPassword', 'validateCurrentPassword', 'on' => self::SCENARIO_DEFAULT],
            ['password', 'validateExistsPassword', 'on' => self::SCENARIO_SET_PASSWORD]
        ];
    }

    public function validateCurrentPassword(): void
    {
        if (!$this->getUser()->validatePassword($this->currentPassword)) {
            $this->addError('currentPassword', \t('Неверный текущий пароль'));
        }
    }

    public function validateExistsPassword(): void
    {
        if ($this->getUser()->password_exists) {
            throw new UserException(\t('Пароль был задан ранее'));
        }
    }

    public function getUser(): ?User
    {
        if (!\Yii::$app->user->isGuest) {
            return \Yii::$app->user->identity;
        }
        return \common\models\User::findByEmail($this->email);
    }

    public function attributeLabels()
    {
        return [
            'currentPassword' => \t('Текущий пароль'),
            'password' => \t('Новый пароль'),
            'repeatPassword' => \t('Повторный пароль')
        ];
    }

    public function formName()
    {
        return '';
    }
}