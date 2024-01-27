<?php namespace common\components\web;

use api\controllers\integrations\WebhooksController;
use api\responses\Response;
use yii\helpers\Json;
use yii\web\ResponseFormatterInterface;

class JsonResponseFormatter implements ResponseFormatterInterface
{
    public function format($response)
    {
        $response->getHeaders()->set('Content-Type', 'application/json; charset=UTF-8');

        $jsonResponse = [
            'code' => $response->getStatusCode()
        ];

        if ($response->data instanceof Response) {
            $response->setStatusCode($response->data->getCode());
            $jsonResponse['code'] = $response->data->getCode();
            $jsonResponse['payload'] = $response->data->getPayload();
        } elseif ($response->data !== null) {
            if ($response->getStatusCode() !== 200) {
                $jsonResponse['payload'] = @$response->data['message'] ?: null;
                if (YII_DEBUG && YII_ENV_DEV) {
                    $jsonResponse['description'] = $response->data;
                }
            } else {
                $jsonResponse['payload'] = $response->data;
            }
        } elseif ($response->content === null) {
            $jsonResponse['payload'] = null;
        }
        if (\Yii::$app->controller instanceof WebhooksController && \Yii::$app->controller->action->id === 'adapty') {
            $response->content = Json::encode($jsonResponse['payload']);
        } else {
            $response->content = Json::encode($jsonResponse);
        }
    }
}