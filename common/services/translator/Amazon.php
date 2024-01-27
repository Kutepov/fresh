<?php namespace common\services\translator;

use Aws\Sdk;
use Aws\Translate\TranslateClient;
use common\contracts\Translator;

class Amazon implements Translator
{
    /** @var TranslateClient */
    private $client;

    public function __construct(Sdk $awsSdk)
    {
        $this->client = $awsSdk->createTranslate();
    }

    public function translate($text, $targetLanguage, $sourceLanguage = 'auto')
    {
        if ($targetLanguage === $sourceLanguage) {
            return $text;
        }

        try {
            $result = $this->client->translateText([
                'SourceLanguageCode' => 'auto',
                'TargetLanguageCode' => $targetLanguage,
                'Text' => $text
            ]);

            return $result->get('TranslatedText');
        } catch (\Throwable $e) {
            return $text;
        }
    }
}