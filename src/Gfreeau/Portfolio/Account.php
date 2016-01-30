<?php

namespace Gfreeau\Portfolio;

class Account
{
    protected $name;
    protected $cashValue = 0;
    protected $holdings = [];
    protected $holdingsValue = 0;

    public function __construct(string $name, float $cashValue, array $holdings)
    {
        $this->name = $name;
        $this->cashValue = $cashValue;

        foreach($holdings as $holding)
        {
            $this->addHolding($holding);
        }
    }

    protected function addHolding(Holding $newHolding)
    {
        if (array_key_exists($newHolding->getSymbol(), $this->holdings)) {
            /** @var Holding $existingHolding */
            $existingHolding = $this->holdings[$newHolding->getSymbol()];

            $this->holdings[$newHolding->getSymbol()] = new Holding(
                $existingHolding->getAssetClass(),
                $existingHolding->getName(),
                $existingHolding->getSymbol(),
                $existingHolding->getQuantity() + $newHolding->getQuantity(),
                $existingHolding->getPrice()
            );
        } else {
            $this->holdings[$newHolding->getSymbol()] = $newHolding;
        }

        $this->holdingsValue += $newHolding->getValue();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCashValue(): float
    {
        return $this->cashValue;
    }

    /**
     * @return Holding[]
     */
    public function getHoldings(): array
    {
        return $this->holdings;
    }

    public function getHoldingsValue(): float
    {
        return $this->holdingsValue;
    }

    public function getAccountValue(): float
    {
        return $this->getCashValue() + $this->getHoldingsValue();
    }
}