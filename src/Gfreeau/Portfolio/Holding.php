<?php

namespace Gfreeau\Portfolio;

class Holding
{
    protected $assetClass;
    protected $name;
    protected $quantity;
    protected $price;
    protected $value;

    public function __construct(AssetClass $assetClass, string $name, int $quantity, float $price)
    {
        if ($quantity < 0) {
            throw new \InvalidArgumentException("price must be greater than 0");
        }

        if ($price < 0) {
            throw new \InvalidArgumentException("price must be greater than 0");
        }

        $this->assetClass = $assetClass;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->value = $this->quantity * $this->price;
    }

    public function getAssetClass(): AssetClass
    {
        return $this->assetClass;
    }

    public function getName(): string
    {
        return $this->name;
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
}