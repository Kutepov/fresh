<?php namespace common\services\users;

use common\forms\Comment;
use common\forms\users\ChangePasswordForm;
use common\forms\users\ProfileForm;
use common\forms\users\RestorePasswordForm;
use common\forms\users\SignInForm;
use common\forms\users\SignUpForm;
use common\models\App;
use common\models\pivot\ArticleRating;
use common\models\pivot\CommentRating;
use common\models\User;
use common\services\NotificationsService;
use Intervention\Image\Constraint;
use Intervention\Image\ImageManager;
use yii;
use yii\helpers\Html;

class UsersService
{
    /** @var NotificationsService */
    private $notificationsService;
    /** @var \Intervention\Image\ImageManager */
    private $imageManager;

    public function __construct(NotificationsService $notificationsService, ImageManager $imageManager)
    {
        $this->notificationsService = $notificationsService;
        $this->imageManager = $imageManager;
    }

    /**
     * Проверка данных авторизации
     * @param SignInForm $form
     * @return User|null
     */
    public function signIn(SignInForm $form): ?User
    {
        if ($form->validate()) {
            return $form->user;
        }

        return null;
    }

    /**
     * Проверка регистрационной формы и регистрация пользователя
     * @param SignUpForm $form
     * @return User|null
     */
    public function signUp(SignUpForm $form): ?User
    {
        if ($form->validate()) {
            if ($form->scenario === SignUpForm::SCENARIO_OAUTH) {
                $form->password = Yii::$app->security->generateRandomString();
            }

            $user = new \common\models\User();
            $user->email = $form->email;
            if (!trim($user->email)) {
                $user->email = null;
            }
            $user->name = $form->name;
            $user->setPassword($form->password);

            if ($form->scenario === SignUpForm::SCENARIO_OAUTH) {
                $user->status = User::STATUS_ACTIVE;
            }
            else {
                $user->status = User::STATUS_INACTIVE;
            }

            $user->geo = geoCountryCode();
            $user->ip = Yii::$app->request->userIP;
            $user->country_code = $form->country;
            $user->useragent = Yii::$app->request->userAgent;
            $user->platform = $form->platform;
            $user->setPassword($form->password);
            $user->password_exists = $form->scenario !== SignUpForm::SCENARIO_OAUTH;
            $user->generateAuthKey();
            $user->generateAccessToken();
            $user->generateConfirmCode();
            $user->generatePasswordResetToken();

            if ($user->save(false)) {
                if ($form->scenario !== SignUpForm::SCENARIO_OAUTH) {
                    $this->notificationsService->sendUserSignUpConfirmNotify($user);
                }
                return $user;
            }
        }

        return null;
    }

    public function sendSignUpConfirmCode(SignUpForm $form): ?User
    {
        if ($form->validate() && $user = User::findByEmail($form->email)) {
            $this->notificationsService->sendUserSignUpConfirmNotify($user);
            return $user;
        }

        return null;
    }

    /**
     * Подтверждение регистрации
     * @return bool
     */
    public function registrationConfirm(SignUpForm $form): ?User
    {
        if ($form->validate() && ($user = User::findByEmail($form->email))) {
            if ($form->code == $user->verification_token) {
                $user->verification_token = null;
                $user->status = User::STATUS_ACTIVE;
                $user->save();

                return $user;
            }
        }

        return null;
    }

    /**
     * Инициация восстановления пароля, отправка письма с кодом подтверждения
     * @param RestorePasswordForm $form
     * @return bool
     */
    public function restorePassword(RestorePasswordForm $form): bool
    {
        if ($form->validate() && $user = $form->getUser()) {
            $user->generateConfirmCode();
            $user->save();

            $this->notificationsService->sendRestorePassConfirmCodeNotify($user);

            return true;
        }

        return false;
    }

    /**
     * Проверка введенного кода подтверждения для изменения (восстановления) пароля
     * @param RestorePasswordForm $form
     */
    public function validateRestorePasswordConfirmationCode(RestorePasswordForm $form): ?User
    {
        $form->setScenario(RestorePasswordForm::SCENARIO_VALIDATE_CODE);

        if ($form->validate()) {
            return $form->getUser();
        }

        return null;
    }

    /**
     * Изменение пароля
     * @param ChangePasswordForm $form
     * @return User|null
     */
    public function changePassword(ChangePasswordForm $form): ?User
    {
        if ($form->scenario === ChangePasswordForm::SCENARIO_RESTORE_PASSWORD) {
            $restorePasswordForm = new RestorePasswordForm([
                'email' => $form->email,
                'code' => $form->code
            ]);

            if ($this->validateRestorePasswordConfirmationCode($restorePasswordForm)) {
                if ($form->validate()) {
                    return $this->doPasswordChange($form);
                }
            }
            else {
                throw new yii\base\UserException(\t('Обратитесь в тех. поддержку'));
            }
        }
        else {
            if ($form->validate()) {
                return $this->doPasswordChange($form);
            }
        }

        return null;
    }

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     * @throws \yii\base\UserException
     */
    public function deleteProfile(ProfileForm $form)
    {
        if ($user = $form->getUser()) {
            \common\models\Comment::updateAll([
                'user_id' => 1
            ], [
                'user_id' => $user->id
            ]);

            $user->delete();
        }
        else {
            throw new yii\base\UserException('User not found');
        }
    }

    /**
     * @throws \yii\base\UserException
     */
    public function updateProfile(ProfileForm $form): ?User
    {
        if ($user = $form->getUser()) {
            if ($form->validate()) {
                switch ($form->scenario) {
                    case ProfileForm::SCENARIO_DEFAULT:
                        $user->name = $form->name;
                        $user->save(false);
                        break;

                    case ProfileForm::SCENARIO_DELETE_PHOTO:
                        $this->deletePhoto($form);
                        break;

                    case ProfileForm::SCENARIO_UPLOAD_PHOTO:
                        $this->uploadPhoto($form);
                        break;
                }

                return $user;
            }

            return null;
        }

        throw new yii\base\UserException('User not found');
    }

    private function uploadPhoto(ProfileForm $form): void
    {
        if ($user = $form->getUser()) {
            if ($user->photo) {
                $this->deletePhoto($form);
            }

            $ext = $form->photo->getExtension();
            $fileName = 'uploads/photo/' . uniqid((string)$user->id, true) . '.' . $ext;
            $filePath = Yii::getAlias('@api/web/' . $fileName);

            $photo = $this->imageManager->make($form->photo->tempName);
            $photo->fit(600, 600, static function (Constraint $constraint) {
                $constraint->upsize();
            });

            if ($photo->save($filePath)) {
                $user->photo = $fileName;
                $user->save(false);
            }
            else {
                throw new yii\base\UserException('An error occurred while uploading a photo');
            }
        }
    }

    private function deletePhoto(ProfileForm $form): void
    {
        if ($user = $form->getUser()) {
            if ($user->photo) {
                @unlink(Yii::getAlias('@api/web/' . $user->photo));
                $user->photo = null;
                $user->save(false);
            }
        }
        else {
            throw new yii\base\UserException('User not found');
        }
    }

    private function doPasswordChange(ChangePasswordForm $form): ?User
    {
        if ($user = $form->getUser()) {
            $user->password_exists = true;
            $user->setPassword($form->password);
            $user->generateAccessToken();
            $user->verification_token = null;
            $user->save();

            return $user;
        }

        return null;
    }

    public function authorizeUser(User $user, bool $remember = true): bool
    {
        return Yii::$app->user->login($user, $remember ? 365 * 86400 : 0);
    }

    public function deauthorizeUser(): void
    {
        Yii::$app->user->logout();
    }

    public function getProfile($id): ?User
    {
        return User::findById($id);
    }
}