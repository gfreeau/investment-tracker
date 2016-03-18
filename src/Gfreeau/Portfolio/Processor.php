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

        $processedAssetClasses = $this->processAssetClasses($config['assetClasses']);

        if ($rebalanceConfig) {
            $config = $this->processRebalance($config, $rebalanceConfig, $stockPrices);
        }

        $accountData = $config['accounts'];

        $stockSymbols = [];

        foreach($accountData as $account) {
            $stockSymbols = array_merge($stockSymbols, array_keys($account['holdings']));
            unset($account);
        }

        $missingSymbols = array_diff($stockSymbols, array_keys($config['shares']));

        if (count($missingSymbols) > 0) {
            throw new BadConfigurationException(sprintf('Missing data for stocks: %s', join(', ', $missingSymbols)));
        }

        if (empty($stockPrices)) {
            $stockPrices = $this->getStockPrices($stockSymbols);
        }

        $processedAccounts = [];

        foreach($accountData as $accountName => $account) {
            $processedHoldings = [];

            foreach($account['holdings'] as $holdingId => $quantity) {
                $holdingAssetClasses = [];

                $holding = $config['shares'][$holdingId];

                if (is_array($holding['assetClass'])) {
                    foreach($holding['assetClass'] as $assetClassName => $percentage) {
                        $holdingAssetClasses[] = [
                            'assetClass' => $processedAssetClasses[$assetClassName],
                            'percentage' => (double) $percentage
                        ];
                    }

                    unset($assetClassName, $percentage);
                } else {
                    $holdingAssetClasses[] = [
                        'assetClass' => $processedAssetClasses[$holding['assetClass']],
                        'percentage' => 1.00
                    ];
                }

                $processedHoldings[] = new Holding(
                    new AssetClassGroup($holdingAssetClasses),
                    $holding['name'],
                    $holding['symbol'],
                    $quantity,
                    $stockPrices[$holding['symbol']]
                );

                unset($holdingAssetClasses, $holding, $holdingId, $quantity);
            }

            $processedAccounts[] = new Account(
                $accountName,
                $account['cash'],
                $processedHoldings
            );

            unset($accountName, $account, $processedHoldings);
        }

        return new Portfolio($processedAssetClasses, $processedAccounts);
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
        $config['shares'] = array_merge_recursive($config['shares'], $rebalanceConfig['shares']);

        $mainAccountListRef = &$config['accounts'];
        $rebalanceAccountList = $rebalanceConfig['accounts'];

        $stockSymbols = [];

        foreach($rebalanceAccountList as $account) {
            $stockSymbols = array_merge($stockSymbols, array_keys($account['buyHoldings']), $account['sellHoldings']);
            unset($account);
        }

        $missingSymbols = array_diff($stockSymbols, array_keys($config['shares']));

        if (count($missingSymbols) > 0) {
            throw new BadConfigurationException(sprintf('Missing data for stocks: %s', join(', ', $missingSymbols)));
        }

        if (empty($stockPrices)) {
            $stockPrices = $this->getStockPrices($stockSymbols);
        }

        foreach($rebalanceAccountList as $accountName => $account) {
            if (!array_key_exists($accountName, $mainAccountListRef)) {
                continue;
            }

            $mainAccountRef = &$mainAccountListRef[$accountName];

            if (!array_key_exists('cash', $mainAccountRef)) {
                $mainAccountRef['cash'] = 0;
            }

            $cashBalance = $mainAccountRef['cash'];
            $cashBalance += $account['contribution'];

            $cost = 0;
            $fees = 0;

            if (!empty($account['sellHoldings'])) {
                $currentHoldings = array_keys($mainAccountRef['holdings']);
                $holdingsToSell = [];
                $sellValue = 0;

                foreach($account['sellHoldings'] as $holdingId) {
                    if (!isset($mainAccountRef['holdings'][$holdingId])) {
                        throw new BadConfigurationException(sprintf('%s does not exist in account %s', $holdingId, $accountName));
                    }

                    $quantity = $mainAccountRef['holdings'][$holdingId];
                    $symbol = $config['shares'][$holdingId]['symbol'];

                    // todo support selling some shares but not all
                    $sellValue += $quantity * $stockPrices[$symbol];
                    $fees += $config['tradingFee'];

                    $holdingsToSell[] = $holdingId;

                    unset($holdingId, $quantity, $symbol);
                }

                $mainAccountRef['holdings'] = array_diff_key($mainAccountRef['holdings'], array_flip($holdingsToSell));
                $cashBalance += $sellValue;

                unset($currentHoldings, $holdingsToSell, $sellValue);
            }

            foreach($account['buyHoldings'] as $holdingId => $quantity) {
                $cost += $quantity * $stockPrices[$config['shares'][$holdingId]['symbol']];
                $fees += $config['tradingFee'];

                unset($holdingId, $quantity);
            }

            $cost += $fees;

            if ($cost > $cashBalance) {
                throw new NotEnoughFundsException(sprintf('The cost of $%4.2f exceeds the available cash of $%4.2f in the "%s" account', $cost, $cashBalance, $accountName));
            }

            $cashBalance -= $cost;

            $mainAccountRef['cash'] = $cashBalance;

            foreach($account['buyHoldings'] as $holdingId => $quantity) {
                if (!isset($mainAccountRef['holdings'][$holdingId])) {
                    $mainAccountRef['holdings'][$holdingId] = 0;
                }

                $mainAccountRef['holdings'][$holdingId] += $quantity;

                unset($holdingId, $quantity);
            }
        }

        // we used references to overwrite config properties
        return $config;
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