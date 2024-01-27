<?php namespace common\components\authclient\clients;

use Firebase\JWT\JWT;
use yii\authclient\OAuth2;

class Apple extends OAuth2
{
    public $teamId;
    public $keyFileId;
    public $keyFilePath;

    /**
     * {@inheritdoc}
     */
    public $authUrl = 'https://appleid.apple.com/auth/authorize';
    /**
     * {@inheritdoc}
     */
    public $tokenUrl = 'https://appleid.apple.com/auth/token';
    /**
     * {@inheritdoc}
     */
    public $apiBaseUrl = 'https://appleid.apple.com/auth/';
    /**
     * {@inheritdoc}
     */
    public $scope = 'name email';

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->clientSecret = $this->generateClientSecret();
    }

    private function generateClientSecret(): string
    {
        $key = file_get_contents(\Yii::getAlias($this->keyFilePath));

        return JWT::encode([
            'iss' => $this->teamId,
            'iat' => time(),
            'exp' => time() + 86400,
            'aud' => 'https://appleid.apple.com',
            'sub' => $this->clientId
        ], $key, 'ES256', $this->keyFileId);
    }

    protected function initUserAttributes()
    {
        $response = $this->getAccessToken()->getParams();
        $userData = explode('.', ($response['oauth_token'] ?? $response['id_token']))[1];
        $userData = (array)json_decode(base64_decode($userData));

        $data = [];
        $data['id'] = $userData['sub'];

        if (isset($_POST['user'])) {
            $user = $_POST['user'];
            $fullName = implode(' ', array_filter([$user['name']['firstName'], $user['name']['lastName']]));
            $data['name'] = $fullName;
        }

        $data['email'] = $userData['email'];

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultName()
    {
        return 'apple';
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultTitle()
    {
        return 'Apple';
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultViewOptions()
    {
        return [
            'popupWidth' => 860,
            'popupHeight' => 680,
        ];
    }

    public function buildAuthUrl(array $params = [])
    {
        $params['response_mode'] = 'form_post';
        return parent::buildAuthUrl($params);
    }
}
