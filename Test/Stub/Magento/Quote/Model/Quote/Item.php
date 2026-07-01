<?php
declare(strict_types=1);

namespace Magento\Quote\Model\Quote;

class Item
{
    public function getParentItemId(): ?int { return null; }
    public function getSku(): string         { return ''; }
    public function getPrice(): float        { return 0.0; }
    public function getQty(): float          { return 0.0; }
    public function getWeight(): float       { return 0.0; }
}
