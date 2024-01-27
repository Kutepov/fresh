<?php namespace buzz\models\traits;

/**
 * @see \common\models\Category
 *
 * @property-read array $route
 */
trait CategoryMixin
{
    public function getRoute(): array
    {
        return ['articles/index', 'categoryName' => $this->name];
    }
}