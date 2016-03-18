<?php

namespace Gfreeau\Portfolio;

use Gfreeau\Portfolio\Exception\BadConfigurationException;
use Gfreeau\Portfolio\Exception\NotEnoughFundsException;
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
     * @param array $rebalanceConfig
     * @param array $stockPrices pass in a list of prices or we will get it from yahoo finance
     * @return Portfolio
     */
    public function process(array $config, array $rebalanceConfig = null, array $stockPrices = null): Portfolio
    {
        // todo validate config

        $assetClasses = $this->processAssetClasses($config['assetClasses']);

        if ($rebalanceConfig) {
            $config = $this->processRebalance($config, $rebalanceConfig, $stockPrices);
        }

        $accountData = $config['accounts'];

        $symbols = $this->getAllStockSymbols($accountData);

        if (empty($stockPrices)) {
            $stockPrices = $this->getStockPrices($symbols);
        }

        // make a copy for use with array_map
        $accounts = $accountData;

        // references are important
        foreach($accounts as $name => &$account) {
            foreach($account['holdings'] as &$holding) {
                $holdingAssetClasses = [];

                if (is_array($holding['assetClass'])) {
                    foreach($holding['assetClass'] as $assetClassName => $percentage) {
                        $holdingAssetClasses[] = [
                            'assetClass' => $assetClasses[$assetClassName],
                            'percentage' => (double) $percentage
                        ];
                    }

                    unset($assetClassName, $percentage);
                } else {
                    $holdingAssetClasses[] = [
                        'assetClass' => $assetClasses[$holding['assetClass']],
                        'percentage' => 1.00
                    ];
                }

                // overwrite value
                $holding = new Holding(
                    new AssetClassGroup($holdingAssetClasses),
                    $holding['name'],
                    $holding['symbol'],
                    $holding['quantity'],
                    $stockPrices[$holding['symbol']]
                );

                unset($holdingAssetClasses);
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
     * @param array $rebalanceConfig
     * @param array [$priceConfig]
     * @return array
     * @throws NotEnoughFundsException
     * @throws BadConfigurationException
     */
    protected function processRebalance(array $config, array $rebalanceConfig, array $stockPrices = null): array
    {
        // todo refactor this monolith

        $rebalanceDefaults = [
            'contribution' => 0,
            'holdings' => [],
            'sellHoldings' => [],
        ];

        $rebalanceConfig = array_merge($rebalanceDefaults, $rebalanceConfig);

        if (!is_array($rebalanceConfig['holdings'])) {
            throw new BadConfigurationException('holdings must be an array');
        }

        if (!is_array($rebalanceConfig['sellHoldings'])) {
            throw new BadConfigurationException('sellHoldings must be an array');
        }

        // reference is important
        $mainAccountData = &$config['accounts'];
        $rebalanceAccountData = $rebalanceConfig['accounts'];

        $symbols = array_merge(
            $this->getAllStockSymbols($mainAccountData),
            $this->getAllStockSymbols($rebalanceAccountData)
        );

        if (empty($stockPrices)) {
            $stockPrices = $this->getStockPrices($symbols);
        }

        foreach($rebalanceAccountData as $name => $account) {
            if (!array_key_exists($name, $mainAccountData)) {
                continue;
            }

            // reference is important
            $mainAccount = &$mainAccountData[$name];

            if (!array_key_exists('cash', $mainAccount)) {
                $mainAccount['cash'] = 0;
            }

            $cashBalance = $mainAccount['cash'];
            $cashBalance += $account['contribution'];

            $fees = 0;

            if (!empty($account['sellHoldings'])) {
                $currentHoldings = array_column($mainAccount['holdings'], 'symbol');
                $holdingsToSell = [];
                $sellValue = 0;

                foreach($account['sellHoldings'] as $sellHolding) {
                    $holdingKey = array_search($sellHolding['symbol'], $currentHoldings);

                    if ($holdingKey === false) {
                        continue;
                    }

                    $holding = $mainAccount['holdings'][$holdingKey];

                    // todo support selling some shares but not all
                    $sellValue += $holding['quantity'] * $stockPrices[$sellHolding['symbol']];
                    $fees += $config['tradingFee'];

                    $holdingsToSell[] = $holdingKey;
                }

                $mainAccount['holdings'] = array_diff_key($mainAccount['holdings'], array_flip($holdingsToSell));
                $cashBalance += $sellValue;

                unset($currentHoldings, $holdingsToSell, $sellValue, $holdingKey, $holding);
            }

            $cost = 0;

            foreach($account['holdings'] as $holding) {
                $cost += $holding['quantity'] * $stockPrices[$holding['symbol']];
                $fees += $config['tradingFee'];
            }

            $cost += $fees;

            if ($cost > $cashBalance) {
                throw new NotEnoughFundsException(sprintf('The cost of $%4.2f exceeds the available cash of $%4.2f in the "%s" account', $cost, $cashBalance, $name));
            }

            $cashBalance -= $cost;

            $mainAccount['cash'] = $cashBalance;

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