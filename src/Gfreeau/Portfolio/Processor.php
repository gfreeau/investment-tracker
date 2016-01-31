<?php

namespace Gfreeau\Portfolio;

use Gfreeau\Portfolio\Exception\ContributionExceededException;
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
     * @param array $contributionConfig
     * @return Portfolio
     */
    public function process(array $config, array $contributionConfig = null): Portfolio
    {
        $assetClasses = $this->processAssetClasses($config['assetClasses']);

        if ($contributionConfig) {
            $config = $this->processContribution($config, $contributionConfig);
        }

        $accountData = $config['accounts'];

        $symbols = $this->getAllStockSymbols($accountData);
        $prices = $this->getStockPrices($symbols);

        // make a copy for use with array_map
        $accounts = $accountData;

        // references are important
        foreach($accounts as $name => &$account) {
            foreach($account['holdings'] as &$holding) {
                // overwrite value
                $holding = new Holding(
                    $assetClasses[$holding['assetClass']],
                    $holding['name'],
                    $holding['symbol'],
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

            unset($name, $account, $holding);
        }

        return new Portfolio($assetClasses, $accounts);
    }

    protected function processAssetClasses(array $assetClasses): array
    {
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

        return $assetClasses;
    }

    /**
     * @param array $config
     * @param array $contributionConfig
     * @return array
     * @throws ContributionExceededException
     */
    protected function processContribution(array $config, array $contributionConfig): array
    {
        // reference is important
        $mainAccountData = &$config['accounts'];
        $contributionAccountData = $contributionConfig['accounts'];

        $symbols = $this->getAllStockSymbols($contributionAccountData);
        $prices = $this->getStockPrices($symbols);

        foreach($contributionAccountData as $name => $account) {
            if (!array_key_exists($name, $mainAccountData)) {
                continue;
            }

            $contribution = (float) $account['contribution'];
            $cost = 0;
            $fees = 0;

            foreach($account['holdings'] as $holding) {
                $cost += $holding['quantity'] * $prices[$holding['symbol']];
                $fees += $config['tradingFee'];
            }

            $cost += $fees;

            // todo factor in any existing cash in the account
            if ($cost > $contribution) {
                throw new ContributionExceededException(sprintf('The cost of $%4.2f exceeds the contribution of $%4.2f to the "%s" account', $cost, $contribution, $name));
            }

            $unusedContribution = $contribution - $cost;

            // reference is important
            $mainAccount = &$mainAccountData[$name];

            if (!array_key_exists('cash', $mainAccount)) {
                $mainAccount['cash'] = 0;
            }

            $mainAccount['cash'] += $unusedContribution;

            $mainAccount['holdings'] = array_merge($mainAccount['holdings'], $account['holdings']);
        }

        return $config;
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
        $symbols = array_unique($symbols);

        // todo check for API exception
        $data = $this->getStockData($symbols)['query']['results']['quote'];

        if (count($symbols) == 1) {
            // need to make the data consistent regardless of if 1 stock or many is requested
            $data = [$data];
        }

        $keys = array_map(function($stockData) {
            return $stockData['Symbol'];
        }, $data);

        $values = array_map(function($stockData) {
            return $stockData['LastTradePriceOnly'];
        }, $data);

        return array_combine($keys, $values);
    }

    protected function getStockData(array $symbols): array {
        return $this->financeClient->getQuotesList($symbols);
    }
}