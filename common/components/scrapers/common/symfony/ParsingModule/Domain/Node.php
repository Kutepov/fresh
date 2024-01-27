<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Domain;

class Node
{
    public const IMAGE_ELEMENT = 'image';
    public const PARAGRAPH_ELEMENT = 'paragraph';

    private $elementName;

    private $value;

    public function __construct(string $elementName, $value)
    {
        $this->elementName = $elementName;
        $this->value = $value;
    }

    public function getElementName(): string
    {
        return $this->elementName;
    }

    public function getValue()
    {
        return $this->value;
    }
}