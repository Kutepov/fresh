<?php namespace common\forms\users;

use common\models\User;
use yii\base\Model;
use yii\base\UserException;

class SignUpForm extends Model
{
    public const SCENARIO_OAUTH = 'oauth';
    public const SCENARIO_CONFIRM = 'confirm';
    public const SCENARIO_RESEND_CONFIRM_CODE = 'resendConfirmCode';

    public $email;
    public $password;
    public $name;
    public $code;
    public $platform;

    public $country;

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => ['email', 'password', 'name', 'platform'],
            self::SCENARIO_OAUTH => ['email', 'name', 'platform'],
            self::SCENARIO_RESEND_CONFIRM_CODE => ['email'],
            self::SCENARIO_CONFIRM => ['email', 'code']
        ];
    }

    public function rules(): array
    {
        return [
            [['email', 'password', 'name'], 'trim'],
            ['email', 'required', 'on' => [self::SCENARIO_DEFAULT, self::SCENARIO_RESEND_CONFIRM_CODE, self::SCENARIO_CONFIRM]],
            ['name', 'required', 'on' => [self::SCENARIO_DEFAULT]],
            ['name', 'string', 'max' => 64],
            ['email', 'email'],
            ['email', 'checkEmail'],
            ['password', 'filter', 'filter' => 'trim'],
            ['password', 'string', 'min' => 6],
            ['password', 'required'],
            ['code', 'trim'],
            ['code', 'required', 'message' => \t('Введите код')],
            ['code', 'validateCode'],
            ['platform', 'in', 'range' => [User::PLATFORM_ANDROID, User::PLATFORM_IOS]]
        ];
    }

    public function validateCode(): void
    {
        if (($user = User::findByEmail($this->email)) && $this->code != $user->verification_token) {
            $this->addError('code', \t('Неверный код'));
        }
    }

    public function checkEmail(): void
    {
        if ($this->email && $user = User::findByEmail($this->email)) {
            if ($this->scenario === self::SCENARIO_DEFAULT) {
                $this->addError('email', \t('E-mail уже зарегистрирован'));
            }
            elseif ($user->status !== User::STATUS_INACTIVE) {
                throw new UserException(\t('Аккаунт был подтвержден ранее'));
            }
        }
        elseif ($this->scenario === self::SCENARIO_CONFIRM || $this->scenario === self::SCENARIO_RESEND_CONFIRM_CODE) {
            throw new UserException(\t('Пользователь не найден'));
        }
    }

    public function attributeLabels(): array
    {
        return [
            'email' => \t('E-mail'),
            'password' => \t('Пароль'),
            'name' => \t('Имя')
        ];
    }

    public function formName(): string
    {
        return '';
    }
}