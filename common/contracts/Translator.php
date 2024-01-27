<?php namespace common\contracts;

interface Translator
{
    public function translate($text, $targetLanguage, $sourceLanguage);
}