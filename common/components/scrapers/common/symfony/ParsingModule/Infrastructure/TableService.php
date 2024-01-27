<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure;

use Symfony\Component\DomCrawler\Crawler;

class TableElement
{
    public $type;
    public $contains;

    public function __construct(string $type, ?array $contains = null)
    {
        $this->type = $type;
        $this->contains = $contains;
    }

    public function addContains(TableElement $tableElement)
    {
        $this->contains[] = $tableElement;
    }
}

class TableService
{
    private $table;

    public function __construct()
    {
        $this->table = new TableElement('table');
    }

    public function getTableElement(Crawler $tableNode)
    {
        $this->getChildren($tableNode);

        return $this->table;
    }

    private function getChildren(Crawler $tableNode, TableElement &$parent = null)
    {
        if (null === $parent) {
            $parent = $this->table;
        }

        $childElements = $tableNode->children();
        $self = $this;

        if ($childElements->count()) {
            $childElements->each(function (Crawler $node) use ($self, &$parent) {
                $newTableElement = new TableElement($node->nodeName());
                $parent->addContains($newTableElement);
                if ($node->children()->count()) {
                    $self->getChildren($node, $newTableElement);
                } else {
                    $newTableElement->addContains(new TableElement('text', [$node->text()]));
                }
            });
        }
    }
}
