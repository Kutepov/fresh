<?php namespace common\services\users;

use common\forms\users\SignUpForm;
use yii\helpers\ArrayHelper;
use yii;
use yii\authclient\ClientInterface;
use common\models\UserSocial;
use common\models\User;

class OAuthService
{
    /**
     * @var ClientInterface|yii\authclient\BaseOAuth
     */
    private $client;
    private $usersService;

    public function __construct(UsersService $usersService)
    {
        $this->usersService = $usersService;
    }

    public function handleWithCode(string $clientId, string $code, ?string $country = null): User
    {
        $this->client = $this->getClient($clientId);
        if ($this->client instanceof yii\authclient\OAuth2) {
            $this->client->fetchAccessToken($code);
        }

        return $this->handle($country);
    }

    /**
     * @throws \Throwable
     * @throws \yii\web\UnauthorizedHttpException
     * @throws \yii\db\StaleObjectException
     * @throws \yii\base\UserException
     */
    public function detach(string $clientId): User
    {
        if (!Yii::$app->user->isGuest) {
            /** @var User $user */
            $user = Yii::$app->user->identity;
            if ($oauthAccount = $user->getOauthAccounts()->where([
                'source' => $clientId
            ])->one()) {
                $oauthAccount->delete();

                return $user;
            }

            throw new yii\base\UserException('Oauth account is not attached to profile');
        }

        throw new yii\web\UnauthorizedHttpException();
    }

    public function handleWithToken(string $clientId, $token, ?string $country = null): User
    {
        $this->client = $this->getClient($clientId);
        $this->client->setAccessToken(new yii\authclient\OAuthToken([
            'params' => ['oauth_token' => $token]
        ]));

        return $this->handle($country);
    }

    public function handleWithClient(ClientInterface $client, ?string $country = null): User
    {
        $this->client = $client;
        return $this->handle($country);
    }

    private function getClient(string $clientId): ClientInterface
    {
        /* @var $clientsCollection \yii\authclient\Collection */
        $clientsCollection = Yii::$app->get('authClientCollection');

        if (!$clientsCollection->hasClient($clientId)) {
            throw new \RuntimeException("Unknown auth client '{$clientId}'");
        }

        return $clientsCollection->getClient($clientId);
    }

    /**
     * @return bool
     * @throws OAuthException
     * @throws yii\db\Exception
     */
    private function handle(?string $country = null): User
    {
        $attributes = $this->client->getUserAttributes();
        $email = ArrayHelper::getValue($attributes, 'email');
        $name = ArrayHelper::getValue($attributes, 'name');
        $id = ArrayHelper::getValue($attributes, 'id');

        /* @var UserSocial $auth */
        $auth = UserSocial::find()->where([
            'source' => $this->client->getId(),
            'source_id' => $id,
        ])->one();

        if (Yii::$app->user->isGuest) {
            if ($auth) { // login
                /* @var User $user */
                $user = $auth->user;
                $this->updateUserInfo($user, $country);

                return $user;
            }
            else { // signup
                if (trim($email) && ($user = User::find()->where(['email' => $email])->one())) {
                    $auth = new UserSocial([
                        'user_id' => $user->id,
                        'source' => $this->client->getId(),
                        'source_id' => (string)$id,
                    ]);

                    if ($auth->save()) {
                        if ($user->status != User::STATUS_ACTIVE) {
                            $user->updateAttributes(['status' => User::STATUS_ACTIVE]);
                        }
                        return $user;
                    }

                    throw new OAuthException(Yii::t('app', "User with the same email as in {client} account already exists but isn't linked to it. Login using email first to link it.", ['client' => $this->client->getTitle()]));
                }
                else {
                    $form = new SignUpForm([
                        'scenario' => SignUpForm::SCENARIO_OAUTH,
                        'email' => $email,
                        'name' => $name,
                        'country' => $country,
                        'platform' => API_PLATFORM
                    ]);

                    if ($user = $this->usersService->signUp($form)) {
                        $auth = new UserSocial([
                            'user_id' => $user->id,
                            'source' => $this->client->getId(),
                            'source_id' => (string)$id,
                        ]);
                        if ($auth->save()) {
                            return $user;
                        }
                        else {
                            throw new OAuthException(Yii::t('app', 'Unable to save {client} account: {errors}', [
                                'client' => $this->client->getTitle(),
                                'errors' => json_encode($auth->getErrors()),
                            ]));
                        }
                    }
                    else {
                        throw new OAuthException(Yii::t('app', 'Unable to save user: {errors}', [
                            'client' => $this->client->getTitle(),
                            'errors' => json_encode($form->getErrors()),
                        ]));
                    }
                }
            }
        }
        else { // user already logged in
            if (!$auth) { // add auth provider
                $auth = new UserSocial([
                    'user_id' => Yii::$app->user->id,
                    'source' => $this->client->getId(),
                    'source_id' => (string)$attributes['id'],
                ]);
                if ($auth->save()) {
                    /** @var User $user */
                    $user = $auth->user;
                    $this->updateUserInfo($user, $country);
                    Yii::$app->getSession()->setFlash('success', [
                        Yii::t('app', 'Linked {client} account.', [
                            'client' => $this->client->getTitle()
                        ]),
                    ]);

                    return $user;
                }
                else {
                    throw new OAuthException(Yii::t('app', 'Unable to link {client} account: {errors}', [
                        'client' => $this->client->getTitle(),
                        'errors' => json_encode($auth->getErrors()),
                    ]));
                }
            }
            else { // there's existing auth
                throw new OAuthException(Yii::t('app',
                    'Unable to link {client} account. There is another user using it.',
                    ['client' => $this->client->getTitle()])
                );
            }
        }
    }

    /**
     * @param User $user
     */
    private function updateUserInfo(User $user, ?string $country = null)
    {
        $attributes = $this->client->getUserAttributes();
        if (!$user->email && $attributes['email'] && !User::findByEmail($attributes['email'])) {
            $user->email = $attributes['email'];
        }

        if (!$user->name && $attributes['name']) {
            $user->name = $attributes['name'];
        }

        if ($country) {
            $user->country_code = $country;
        }

        $user->save(false);
    }
}