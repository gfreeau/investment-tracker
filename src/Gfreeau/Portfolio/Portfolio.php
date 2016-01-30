<?php

namespace Gfreeau\Portfolio;

class Portfolio
{
    protected $assetClasses = [];
    protected $accounts = [];
    protected $cashValue = 0;
    protected $holdingsValue = 0;
    protected $totalValue = 0;

    public function __construct(array $assetClasses, array $accounts)
    {
        foreach($assetClasses as $assetClass) {
            $this->addAssetClass($assetClass);
        }

        foreach($accounts as $account) {
            $this->addAccount($account);
        }
    }

    protected function addAssetClass(AssetClass $assetClass)
    {
        $this->assetClasses[] = $assetClass;
    }

    protected function addAccount(Account $account)
    {
        $this->accounts[] = $account;
        $this->cashValue += $account->getCashValue();
        $this->holdingsValue += $account->getHoldingsValue();
        $this->totalValue += $account->getAccountValue();
    }

    /**
     * @return Account[]
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    /**
     * @return AssetClass[]
     */
    public function getAssetClasses(): array
    {
        return $this->assetClasses;
    }

    public function getCashValue(): float
    {
        return $this->cashValue;
    }

    public function getHoldingsValue(): float
    {
        return $this->holdingsValue;
    }

    public function getTotalValue(): float
    {
        return $this->totalValue;
    }

    /**
     * @return Holding[]
     */
    public function getAllHoldings(): array
    {
        $holdings = [];

        foreach($this->getAccounts() as $account) {
            $holdings = array_merge($holdings, $account->getHoldings());
        }

        return $holdings;
    }
}