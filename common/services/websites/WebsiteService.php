<?php declare(strict_types=1);

namespace common\services\websites;

use GuzzleHttp\Client;

class WebsiteService
{
    private $curl;

    public function __construct(Client $curl)
    {
        $this->curl = $curl;
    }


}