<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PageLoader
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function loadPageContent(string $url): string
    {
        $response = $this->httpClient->request('GET', $url);

        return $response->getContent();
    }
}
