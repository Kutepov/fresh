<?php declare(strict_types=1);

namespace common\services\adapty;

use Carbon\Carbon;

class SubscriptionStatus
{
    private bool $isActive;
    private ?Carbon $startedAt;
    private ?Carbon $expiresAt;

    /**
     * @param $isActive
     * @param $startedAt
     * @param $expiresAt
     */
    public function __construct(bool $isActive, ?string $startedAt = null, ?string $expiresAt = null)
    {
        $this->isActive = $isActive;
        $this->startedAt = Carbon::parse($startedAt);
        $this->expiresAt = Carbon::parse($expiresAt);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getStartedAt(): ?Carbon
    {
        return $this->startedAt;
    }

    public function getExpiresAt(): ?Carbon
    {
        return $this->expiresAt;
    }
}