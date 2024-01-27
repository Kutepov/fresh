<?php namespace common\components\authclient;

use yii\authclient\OAuth2;
use yii\base\Exception;
use yii\web\Response;
use yii;

class AuthAction extends \yii\authclient\AuthAction
{
    /**
     * Performs OAuth2 auth flow.
     * @param OAuth2 $client auth client instance.
     * @param array $authUrlParams additional auth GET params.
     * @return Response action response.
     * @throws \yii\base\Exception on failure.
     */
    protected function authOAuth2($client, $authUrlParams = [])
    {
        $request = Yii::$app->getRequest();

        if ($request->isGet) {
            $reqError = $request->get('error');
        }
        else {
            $reqError = $request->post('error');
        }

        if (($error = $reqError) !== null) {
            if (
                $error === 'access_denied' ||
                $error === 'user_cancelled_login' ||
                $error === 'user_cancelled_authorize'
            ) {
                // user denied error
                return $this->authCancel($client);
            }
            // request error
            if ($request->isGet) {
                $errorMessage = $request->get('error_description', $request->get('error_message'));
            }
            else {
                $errorMessage = $request->post('error_description', $request->post('error_message'));
            }

            if ($errorMessage === null) {
                if ($request->isGet)
                    $errorMessage = http_build_query($request->get());
                else
                    $errorMessage = http_build_query($request->post());
            }
            throw new Exception('Auth error: ' . $errorMessage);
        }

        if ($request->isGet) {
            $reqCode = $request->get('code');
        }
        else {
            $reqCode = $request->post('code');
        }

        // Get the access_token and save them to the session.
        if (($code = $reqCode) !== null) {
            $token = $client->fetchAccessToken($code);
            if (!empty($token)) {
                return $this->authSuccess($client);
            }
            return $this->authCancel($client);
        }

        $url = $client->buildAuthUrl($authUrlParams);
        return Yii::$app->getResponse()->redirect($url);
    }
}