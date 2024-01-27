<?php namespace common\contracts;

/**
 * @property-read \common\contracts\RatingObject[] $currentUserRating
 */
interface RateableEntity
{
    public function getId();

    public function getRatingValue(): ?int;

    public function getCurrentUserRating(): RateableEntityQuery;
}