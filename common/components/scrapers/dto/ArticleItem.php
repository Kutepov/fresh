<?php namespace common\components\scrapers\dto;

use Carbon\Carbon;
use Assert\Assertion;

class ArticleItem
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $title;

    /** @var Carbon|\DateTimeImmutable */
    private $publicationDate;

    /**
     * @var string|null
     */
    private $previewImage;

    /** @var ArticleBody|null */
    private $body = null;

    /** @var string */
    private $description = null;

    /** @var null|string */
    private $type = null;

    private $sourceName;
    private $sourceDomain;

    public function __construct(string $url, string $title, $publicationDate, ?string $previewImage = null, ?string $type = null)
    {
        Assertion::url($url);
        Assertion::notBlank($title);

        //legacy
        if ($publicationDate instanceof \DateTimeImmutable || $publicationDate instanceof \DateTime) {
            $publicationDate = Carbon::instance($publicationDate);
        }

        $this->url = removeUtmParams($url);
        $this->title = $title;
        $this->publicationDate = $publicationDate;
        $this->previewImage = $previewImage;
        $this->type = $type;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPublicationDate(): Carbon
    {
        return $this->publicationDate;
    }

    public function getPreviewImage(): ?string
    {
        if (is_null($this->previewImage)) {
            if (!is_null($body = $this->getBody())) {
                return $body->getFirstImage();
            }

            return null;
        }

        return $this->previewImage;
    }

    /**
     * @return ArticleBody
     */
    public function getBody(): ?ArticleBody
    {
        return $this->body;
    }

    public function setPreviewImage(?string $previewImage): void
    {
        $this->previewImage = $previewImage;
    }

    /**
     * @param ArticleBody $body
     */
    public function setBody(ArticleBody $body): void
    {
        $this->body = $body;
    }

    /**
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param string|null $type
     */
    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getSourceName()
    {
        return $this->sourceName;
    }

    /**
     * @param mixed $sourceName
     */
    public function setSourceName($sourceName): void
    {
        $this->sourceName = $sourceName;
    }

    /**
     * @return mixed
     */
    public function getSourceDomain()
    {
        return $this->sourceDomain;
    }

    /**
     * @param mixed $sourceDomain
     */
    public function setSourceDomain($sourceDomain): void
    {
        $this->sourceDomain = $sourceDomain;
    }
}
