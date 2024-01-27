<?php namespace common\components\scrapers\common;

/**
 * @Annotation
 * @Target("CLASS")
 */
final class Config
{
    /** @var string */
    public $timezone;

    /** @var array */
    public $urls;
}