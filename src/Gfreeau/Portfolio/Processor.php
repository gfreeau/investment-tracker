<?php

namespace Gfreeau\Portfolio;

use Scheb\YahooFinanceApi\ApiClient;

class Processor
{
    /**
     * @var ApiClient
     */
    private $financeClient;

    public function __construct(ApiClient $financeClient)
    {

        $this->financeClient = $financeClient;
    }

    /**
     * @param array $config
     * @return Portfolio
     */
    public function process(array $config)
    {
        $assetClasses = $config['assetClasses'];
        $totalAllocation = 0;

        foreach($assetClasses as $name => &$value) {
            $assetClass = new AssetClass($name, $value);

            $totalAllocation += $assetClass->getTargetAllocation();

            // overwrite value
            $value = $assetClass;
        }

        unset($name, $value, $assetClass);

        if ($totalAllocation > 1.00) {
            throw new \RuntimeException('total allocation is greater than 100%');
        }

        $accountData = $config['accounts'];

        $symbols = $this->getAllStockSymbols($accountData);
        $prices = $this->getStockPrices($symbols);

        $accounts = $accountData;

        foreach($accounts as $name => &$account) {
            foreach($account['holdings'] as &$holding) {
                // overwrite value
                $holding = new Holding(
                    $assetClasses[$holding['assetClass']],
                    $holding['name'],
                    $holding['quantity'],
                    $prices[$holding['symbol']]
                );
            }

            // overwrite value
            $account = new Account(
                $name,
                $account['cash'],
                $account['holdings']
            );
        }

        unset($name, $account, $holding);

        return new Portfolio($assetClasses, $accounts);
    }

    protected function getAllStockSymbols(array $accounts): array
    {
        $symbols = [];

        foreach($accounts as $account) {
            $symbols = array_merge($symbols, array_map(function($holding) {
                return $holding['symbol'];
            }, $account['holdings']));
        }

        return $symbols;
    }

    protected function getStockPrices(array $symbols): array {
        // todo check for API exception
        $data = $this->getStockData($symbols)['query']['results']['quote'];

        $data = array_map(function($stockData) {
            return $stockData['LastTradePriceOnly'];
        }, $data);

        return array_combine($symbols, $data);
    }

    protected function getStockData(array $symbols): array {
        return $this->financeClient->getQuotesList($symbols);
    }
}