<?php namespace api\responses;

class ValidationErrorBadResponse extends Response
{
    public function __construct($payload, $code = 422)
    {
        $payload = ['errors' => $payload];
        parent::__construct($payload, $code);
    }
}