<?php namespace api\controllers\user;

use api\controllers\Controller;
use common\forms\users\ChangePasswordForm;
use common\models\User;
use common\services\users\UsersService;
use api\responses\ValidationErrorBadResponse;
use yii;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\Request;
use yii\base\UserException;
use common\forms\users\ProfileForm;

class ProfileController extends Controller
{
    /** @var UsersService */
    private $service;

    public function __construct($id,
                                $module,
                                UsersService $service,
                                $config = [])
    {
        $this->service = $service;

        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = ArrayHelper::merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'photo' => ['post', 'delete'],
                    'get' => ['get'],
                    'delete' => ['delete'],
                    '*' => ['post']
                ]
            ]
        ]);

        $behaviors['authenticator']['optional'] = ['get'];

        return $behaviors;
    }

    /**
     * @throws \yii\web\NotFoundHttpException
     * @throws \yii\web\UnauthorizedHttpException
     */
    public function actionGet($id = null)
    {
        /** @var User $user */
        if ($id) {
            if (!($user = $this->service->getProfile($id))) {
                throw new yii\web\NotFoundHttpException(\t('Пользователь не найден'));
            }
        }
        else {
            if (Yii::$app->user->isGuest) {
                throw new yii\web\UnauthorizedHttpException();
            }

            $user = Yii::$app->user->identity;
            $user->scenario = User::SCENARIO_API_AUTH;
        }

        return $user;
    }

    /**
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionIndex(Request $request)
    {
        $form = new ProfileForm();
        $form->load($request->post());

        try {
            if ($user = $this->service->updateProfile($form)) {
                $user->scenario = User::SCENARIO_API_AUTH;
                return $user;
            }
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }

    /**
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDelete(Request $request): bool
    {
        $form = new ProfileForm([
            'scenario' => ProfileForm::SCENARIO_DELETE_PROFILE
        ]);

        $form->load($request->post());

        try {
            $this->service->deleteProfile($form);
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }

        return true;
    }

    /**
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionPhoto(Request $request)
    {
        $form = new ProfileForm();
        if ($request->isPost) {
            $form->scenario = ProfileForm::SCENARIO_UPLOAD_PHOTO;
            $form->photo = yii\web\UploadedFile::getInstance($form, 'photo');
        }
        elseif ($request->isDelete) {
            $form->scenario = ProfileForm::SCENARIO_DELETE_PHOTO;
        }

        try {
            if ($user = $this->service->updateProfile($form)) {
                $user->scenario = User::SCENARIO_API_AUTH;
                return $user;
            }
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }

    public function actionSetPassword(Request $request)
    {
        $form = new ChangePasswordForm(['scenario' => ChangePasswordForm::SCENARIO_SET_PASSWORD]);
        $form->load($request->post());

        try {
            if ($user = $this->service->changePassword($form)) {
                $user->scenario = User::SCENARIO_API_AUTH;
                return $user;
            }
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }

    public function actionChangePassword(Request $request)
    {
        $form = new ChangePasswordForm();
        $form->load($request->post());

        try {
            if ($user = $this->service->changePassword($form)) {
                $user->scenario = User::SCENARIO_API_AUTH;
                return $user;
            }
        } catch (UserException $e) {
            throw new yii\web\BadRequestHttpException($e->getMessage());
        }

        return new ValidationErrorBadResponse($form->firstErrors);
    }
}