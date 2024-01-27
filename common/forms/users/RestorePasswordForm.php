<?php namespace common\forms\users;

use common\models\User;
use yii\base\Model;
use yii\base\UserException;

class RestorePasswordForm extends Model
{
    public const SCENARIO_VALIDATE_CODE = 'validateCode';

    public $email;
    public $code;

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => ['email'],
            self::SCENARIO_VALIDATE_CODE => ['email', 'code']
        ];
    }

    public function rules()
    {
        return [
            [['email', 'code'], 'trim'],
            ['email', 'required'],
            ['code', 'required', 'message' => \t('Введите код')],
            ['code', 'validateCode']
        ];
    }

    public function validateCode()
    {
        if (($user = $this->getUser()) && $this->code != $user->verification_token) {
            $this->addError('code', \t('Неверный код'));
        }
    }

    public function afterValidate()
    {
        $user = $this->getUser();

        if (!$this->hasErrors()) {
            if (!$user) {
                $this->addError('email', \t('Пользователь не найден'));
            }
            elseif ($user->status === User::STATUS_BANNED || $user->apps[0]->banned) {
                $this->addError('email', \t('Ваш аккаунт заблокирован'));
            }
        }

        if ($this->scenario === self::SCENARIO_VALIDATE_CODE && $this->hasErrors('email')) {
            throw new UserException($this->getFirstError('email'));
        }
    }

    public function getUser(): ?\common\models\User
    {
        return User::findByEmail($this->email);
    }

    public function attributeLabels()
    {
        return [
            'email' => 'E-mail',
            'code' => \t('Код подтверждения')
        ];
    }

    public function formName()
    {
        return '';
    }
}