<?php namespace api\controllers\user;

use api\controllers\Controller;
use common\forms\users\ChangePasswordForm;
use common\forms\users\RestorePasswordForm;
use common\forms\users\SignInForm;
use common\forms\users\SignUpForm;
use common\models\User;
use common\services\users\OAuthService;
use common\services\users\UsersService;
use api\responses\ValidationErrorBadResponse;
use yii;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\Request;
use yii\base\UserException;

class CredentialsController extends Controller
{
    /** @var OAuthService */
    private $oauthService;
    /** @var UsersService */
    private $usersService;

    public function __construct($id,
                                $module,
                                OAuthService $oauthService,
                                UsersService $usersService,
                                $config = [])
    {
        $this->oauthService = $oauthService;
        $this->usersService = $usersService;

        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = ArrayHelper::merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    '*' => ['post']
                ]
            ]
        ]);

        $behaviors['authenticator']['optional'] = ['auth'];
        $behaviors['authenticator']['only'] = [
            'auth',
            'auth-detach'
        ];

        return $behaviors;
    }

    /**
     * Авторизация
     * @param Request $request
     * @return \api\responses\ValidationErrorBadResponse|\common\models\User|null
     */
    public function actionSignIn(Request $request)
    {
        $form = new SignInForm();
        $form->load($request->post());
        $form->country = $this->country;

        if ($user = $this->usersService->signIn($form)) {
            $user->scenario = User::SCENARIO_API_AUTH;
            return $user;
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }

    /**
     * Регистрация
     * @param \yii\web\Request $request
     * @return \api\responses\ValidationErrorBadResponse|\common\models\User|null
     */
    public function actionSignUp(Request $request)
    {
        $form = new SignUpForm();
        $form->load($request->post());
        $form->country = $this->country;
        $form->platform = API_PLATFORM;

        if ($user = $this->usersService->signUp($form)) {
            $user->scenario = User::SCENARIO_API_AUTH;
            return $user;
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }

    /**
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSendSignUpConfirmCode(Request $request)
    {
        $form = new SignUpForm([
            'scenario' => SignUpForm::SCENARIO_RESEND_CONFIRM_CODE
        ]);

        $form->load($request->post());

        try {
            if ($user = $this->usersService->sendSignUpConfirmCode($form)) {
                $user->scenario = User::SCENARIO_API_AUTH;
                return null;
            }
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }

    /**
     * Подтверждение регистрации
     * @param \yii\web\Request $request
     * @return \api\responses\ValidationErrorBadResponse|bool|\common\models\User|null
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSignUpConfirm(Request $request)
    {
        $form = new SignUpForm([
            'scenario' => SignUpForm::SCENARIO_CONFIRM
        ]);

        $form->load($request->post());

        try {
            if ($user = $this->usersService->registrationConfirm($form)) {
                $user->scenario = User::SCENARIO_API_AUTH;
                return $user;
            }
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }

    /**
     * Запрос восстановления пароля
     * @param \yii\web\Request $request
     * @return \api\responses\ValidationErrorBadResponse|array
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRestorePassword(Request $request)
    {
        $form = new RestorePasswordForm();
        $form->load($request->post());

        try {
            if ($this->usersService->restorePassword($form)) {
                return null;
            }
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }

    /**
     * Подтверждение восстановления пароля
     * @param \yii\web\Request $request
     * @return \api\responses\ValidationErrorBadResponse|null
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRestorePasswordConfirm(Request $request)
    {
        $form = new RestorePasswordForm([
            'scenario' => RestorePasswordForm::SCENARIO_VALIDATE_CODE
        ]);
        $form->load($request->post());

        try {
            if ($this->usersService->validateRestorePasswordConfirmationCode($form)) {
                return null;
            }
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }

    /**
     * Изменение пароля
     * @param \yii\web\Request $request
     * @return \api\responses\ValidationErrorBadResponse|\common\models\User|null
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionChangePassword(Request $request)
    {
        $form = new ChangePasswordForm(['scenario' => ChangePasswordForm::SCENARIO_RESTORE_PASSWORD]);
        $form->load($request->post());

        try {
            if ($user = $this->usersService->changePassword($form)) {
                $user->scenario = User::SCENARIO_API_AUTH;
                return $user;
            }
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }

    /**
     * OAuth авторизация
     * @param $clientId
     * @param \yii\web\Request $request
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionAuth($clientId, Request $request)
    {
        try {
            if ($token = $request->post('token')) {
                $user = $this->oauthService->handleWithToken($clientId, $token, $this->country);
            }
            elseif ($code = $request->post('code')) {
                $user = $this->oauthService->handleWithCode($clientId, $code, $this->country);
            }
            else {
                throw new yii\base\Exception('Token and Code is empty.');
            }

            $user->scenario = User::SCENARIO_API_AUTH;
            return $user;
        } catch (\Exception $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionAuthDetach($clientId): User
    {
        try {
            $user = $this->oauthService->detach($clientId);

            $user->scenario = User::SCENARIO_API_AUTH;
            return $user;
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }
    }
}