<?php declare(strict_types=1);

namespace common\services\apps\entities;

interface EventContract
{
    public function getEvent(): string;

    public function getEventDate(): int;

    public function getAmount(): ?float;

    public function getCurrency(): ?string;
}