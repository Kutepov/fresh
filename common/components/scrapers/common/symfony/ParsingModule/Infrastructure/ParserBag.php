<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\Exception\MissingParserException;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\SiteParser\ParserInterface;
use function array_key_exists;

class ParserBag
{
    private $parsers = [];

    public function get(string $host): ParserInterface
    {
        if (!array_key_exists($host, $this->parsers)) {
            throw new MissingParserException("Missing parser for host $host");
        }

        return $this->parsers[$host];
    }

    public function add(ParserInterface $parser): void
    {
        $this->parsers[$parser->getHost()] = $parser;
    }
}
