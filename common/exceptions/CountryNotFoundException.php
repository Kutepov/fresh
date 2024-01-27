<?php namespace common\exceptions;

class CountryNotFoundException extends \Exception
{
    public $message = 'Country not found';
}