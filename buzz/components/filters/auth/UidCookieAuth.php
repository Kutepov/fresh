<?php namespace buzz\components\filters\auth;

use common\models\App;
use common\services\AppsService;
use yii\filters\auth\AuthMethod;
use yii\web\IdentityInterface;
use yii\web\Request;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii;

class UidCookieAuth extends AuthMethod
{
    public $optional = ['*'];

    /** @var AppsService */
    private $service;

    public function init()
    {
        $this->service = Yii::$container->get(AppsService::class);
        parent::init();
    }

    /**
     * Authenticates the current user.
     * @param \yii\web\User $user
     * @param Request $request
     * @param Response $response
     * @return IdentityInterface the authenticated user identity. If authentication information is not provided, null will be returned.
     * @throws UnauthorizedHttpException if authentication information is provided but is invalid.
     */
    public function authenticate($user, $request, $response)
    {
        $cookieLifetime = 86400 * 365 * 10;
        $uid = $request->cookies->get('sdud');

        if (!$this->service->validateApp(App::PLATFORM_WEB, $uid)) {
            $uid = null;
        }

        if (!$uid) {
            $uid = md5(microtime(true) . mt_rand() . mt_rand() . mt_rand());
            $response->cookies->add(new yii\web\Cookie([
                'name' => 'sdud',
                'value' => $uid,
                'expire' => time() + $cookieLifetime
            ]));
        }

        if ($app = $this->service->findOrCreate(
            App::PLATFORM_WEB,
            $uid,
            null,
            CURRENT_COUNTRY,
            CURRENT_LANGUAGE,
            CURRENT_ARTICLES_LANGUAGE
        )) {
            $user->switchIdentity($app, $cookieLifetime);
            define('API_APP_ID', $app->id);
            return $app;
        }

        return null;
    }
}