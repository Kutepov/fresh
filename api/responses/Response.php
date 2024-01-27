<?php namespace api\responses;

abstract class Response
{
    private $code;
    private $payload;

    public function __construct($payload, int $code = 200)
    {
        $this->code = $code;
        $this->payload = $payload;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getPayload()
    {
        return $this->payload;
    }
}