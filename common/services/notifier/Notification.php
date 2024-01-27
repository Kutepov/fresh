<?php namespace common\services\notifier;

class Notification
{
    private $title;
    private $lines = [];
    private $changed = false;
    private $isMonitoring = false;
    private $adaptyPlatform = null;
    private $mute = false;

    public function __construct(?string $title, ?string $text = null, ?bool $isMonitoring = false, ?bool $mute = false, ?string $adaptyPlatform = null)
    {
        $this->mute = $mute;
        $this->title = $title;
        if (!is_null($text)) {
            $this->lines[] = $text;
        }
        $this->isMonitoring = $isMonitoring;
        $this->adaptyPlatform = $adaptyPlatform;
    }

    public function addLine(?string $line): void
    {
        $this->changed = true;
        $this->lines[] = $line;
    }

    public function getLines(): array
    {
        return $this->lines;
    }

    public function getUrlEncodedNotificationBody(): string
    {
        return rawurlencode($this->getNotificationBody());
    }

    public function getNotificationBody(): string
    {
        $result = array_merge(
            $this->getTitle() ? ['<b>' . $this->getTitle() . '</b>'] : [],
            $this->lines
        );

        return implode(PHP_EOL, $result);
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function isChanged(): bool
    {
        return $this->changed;
    }

    public function isMonitoring(): bool
    {
        return $this->isMonitoring;
    }

    public function getAdaptyPlatform(): ?string
    {
        return $this->adaptyPlatform;
    }

    public function isMuted(): bool
    {
        return $this->mute;
    }
}