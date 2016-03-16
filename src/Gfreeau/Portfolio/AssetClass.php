<?php

namespace Gfreeau\Portfolio;

class AssetClass
{
    protected $name;
    protected $targetAllocation;

    public function __construct(string $name, float $targetAllocation)
    {
        $this->name = $name;

        $inRange = (0 <= $targetAllocation) && ($targetAllocation <= 1);

        if (!$inRange) {
            throw new \InvalidArgumentException('target allocation must be between 0 and 1');
        }

        $this->targetAllocation = $targetAllocation;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTargetAllocation(): float
    {
        return $this->targetAllocation;
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}