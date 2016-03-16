<?php

namespace Gfreeau\Portfolio;

class Holding
{
    protected $assetClassGroup;
    protected $name;
    protected $symbol;
    protected $quantity;
    protected $price;
    protected $value;

    /**
     * Holding constructor.
     * @param AssetClassGroup $assetClassGroup
     * @param string $name
     * @param string $symbol
     * @param int $quantity
     * @param float $price
     */
    public function __construct(AssetClassGroup $assetClassGroup, string $name, string $symbol, int $quantity, float $price)
    {
        if ($quantity < 0) {
            throw new \InvalidArgumentException("price must be greater than 0");
        }

        if ($price < 0) {
            throw new \InvalidArgumentException("price must be greater than 0");
        }

        $this->assetClassGroup = $assetClassGroup;
        $this->name = $name;
        $this->symbol = $symbol;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->value = $this->quantity * $this->price;
    }

    public function getAssetClassGroup(): AssetClassGroup
    {
        return $this->assetClassGroup;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getAssetClassValue(AssetClass $assetClass): float
    {
        $percentage = $this->assetClassGroup->getPercentage($assetClass);
        return $this->getValue() * $percentage;
    }
}