<?php namespace common\services\translator;

trait TranslatorTrait
{
    private $specMatches = [];

    private function beforeTranslate($text): string
    {
        $i = 0;
        return preg_replace_callback('#(\([A-Z]{2}\))#', function ($matches) use (&$i) {
            $this->specMatches[$i] = $matches[1];
            $result = '(# ' . $i . ' #)';
            $i++;
            return $result;
        }, $text);

    }

    private function afterTranslate($text): string
    {
        foreach ($this->specMatches as $i => $specMatch) {
            $text = preg_replace('#\(\# ' . $i . ' \#\)#', $specMatch, $text);
        }

        return $text;
    }
}