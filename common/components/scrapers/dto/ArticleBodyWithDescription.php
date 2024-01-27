<?php namespace common\components\scrapers\dto;

class ArticleBodyWithDescription extends ArticleBody
{
    /**
     * @var string
     */
    private $description;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }
}