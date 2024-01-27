<?php namespace common\components\scrapers\dto;

class ArticleBodyNode implements \JsonSerializable
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

    public function jsonSerialize()
    {
        return [
            'value' => $this->value,
            'elementName' => $this->elementName,
        ];
    }
}