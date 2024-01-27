<?php namespace common\services;
use Yii;

class ClassFinder
{
    public function findClassesInNamespace($namespace)
    {
        $key = 'source_classes_'.$namespace;
        $cache = Yii::$app->cache;

        if ($cache->exists($key)) {
            return Yii::$app->cache->get($key);
        }

        $classes = \HaydenPierce\ClassFinder\ClassFinder::getClassesInNamespace(
            $namespace,
            \HaydenPierce\ClassFinder\ClassFinder::RECURSIVE_MODE
        );
        $cache->set($key, $classes);
        return $classes;
    }
}