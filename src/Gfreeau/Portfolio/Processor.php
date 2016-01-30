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

        if ($contributionConfig) {
            $contributionConfig = $this->processContribution($contributionConfig, (float) $config['tradingFee']);

            foreach($contributionConfig['accounts'] as $name => $tempAccount) {
                if (!array_key_exists($name, $accountData)) {
                    continue;
                }

                $thisAccount = &$accountData[$name];

                if (!array_key_exists('cash', $thisAccount)) {
                    $thisAccount['cash'] = 0;
                }

                $thisAccount['cash'] += $tempAccount['unusedContribution'];

                $thisAccount['holdings'] = array_merge($thisAccount['holdings'], $tempAccount['holdings']);

                unset($thisAccount);
            }

            unset($name, $tempAccount);
        }

        $symbols = $this->getAllStockSymbols($accountData);
        $prices = $this->getStockPrices($symbols);

        $accounts = $accountData;

        foreach($accounts as $name => &$tempAccount) {
            foreach($tempAccount['holdings'] as &$holding) {
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
            $tempAccount = new Account(
                $name,
                $tempAccount['cash'],
                $tempAccount['holdings']
            );

            unset($name, $tempAccount, $holding);
        }

        return new Portfolio($assetClasses, $accounts);
    }

    /**
     * @param array $config
     * @param float $tradingFee
     * @return array
     * @throws ContributionExceededException
     */
    protected function processContribution(array $config, float $tradingFee): array
    {
        $accountData = $config['accounts'];

        $symbols = $this->getAllStockSymbols($accountData);
        $prices = $this->getStockPrices($symbols);

        foreach($config['accounts'] as $name => &$account) {
            $contribution = (float) $account['contribution'];
            $cost = 0;
            $fees = 0;

            foreach($account['holdings'] as $holding) {
                $cost += $holding['quantity'] * $prices[$holding['symbol']];
                $fees += $tradingFee;
            }

            $cost += $fees;

            // todo factor in any existing cash in the account
            if ($cost > $contribution) {
                throw new ContributionExceededException(sprintf('The contribution of %4.2f exceeds the contribution of %4.2f to the "%s" account', $cost, $contribution, $name));
            }

            $account['unusedContribution'] = ($contribution - $cost);
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