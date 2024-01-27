<?php

declare(strict_types=1);

namespace common\components\scrapers\common\services;

use Assert\Assertion;

class HashImageService
{
    /**
     * @var string
     */
    private $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function hashImage(string $url): string
    {
        $url = trim($url);

        if (preg_match('#^//#', $url)) {
            $url = 'https:' . $url;
        }

        Assertion::regex($url, '#^https?://#i', 'HashImageService: wrong image url: %s');

        $alg = 'AES-128-CBC';
        $key = $this->key;
        /** @noinspection EncryptionInitializationVectorRandomnessInspection */
        $crypt = openssl_encrypt($url, $alg, $key, 0, $key);

        return urlencode(rtrim(strtr($crypt, '+/', '-_'), '=')) . '.jpg';
    }

    public function decrypt($string)
    {
        $alg = 'AES-128-CBC';
        $string = (strtr($string, '-_', '+/'));
        return openssl_decrypt($string, $alg, $this->key, 0, $this->key);
    }
}
