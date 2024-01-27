<?php namespace common\services\translator;

use Google\Cloud\Translate\V2\TranslateClient;
use yii\base\Exception;
use common\contracts\Translator;

class Google implements Translator
{
    use TranslatorTrait;

    private $client;

    public function __construct(TranslateClient $client)
    {
        $this->client = $client;
    }

    public function translate($text, $targetLanguage, $sourceLanguage = null): ?string
    {
        if ($targetLanguage === $sourceLanguage || !trim($text)) {
            return $text;
        }

        $text = $this->beforeTranslate($text);

        try {
            $result = $this->client->translate($text, [
                'target' => $targetLanguage
            ]);

            $result = trim($result['text']);

            if (empty($result)) {
                throw new Exception('Empty translation');
            }
        } catch (\Exception $e) {
            \Yii::error($e);
            return null;
        }

        return $this->afterTranslate($result);
    }
}