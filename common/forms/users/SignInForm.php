<?php namespace common\forms\users;

use common\models\User;
use yii\base\Model;

/**
 * Class SignInForm
 * @package common\forms\users
 * @property-read \common\models\User $user
 */

class SignInForm extends Model
{
	public $email;
	public $password;
	public $rememberMe = true;

	public $country;

	public function rules(): array
	{
		return [
		    [['email', 'password'], 'trim'],
			[['email', 'password'], 'required'],
			['email', 'email'],
			['password', 'validatePassword'],
			['rememberMe', 'boolean']
		];
	}

	public function validatePassword(): void
	{
		$this->password = trim($this->password);
		if (!$this->hasErrors()) {
			$user = $this->user;
			if (!$user || !$user->validatePassword($this->password)) {
				$this->addError('password', \t('Неверный логин или пароль'));
			} elseif ($user->status === User::STATUS_BANNED || $user->apps[0]->banned) {
				$this->addError('email', \t('Ваш аккаунт заблокирован'));
			}
			elseif ($this->country) {
			    $user->updateAttributes([
			        'country_code' => $this->country
                ]);
            }
		}
	}

	public function getUser(): ?\common\models\User {
	    return User::findByEmail($this->email);
    }

	public function attributeLabels(): array
	{
		return [
			'email' => \t('E-mail'),
			'password' => \t('Пароль'),
			'rememberMe' => \t('Запомнить меня'),
		];
	}

	public function formName(): string
    {
        return '';
    }
}