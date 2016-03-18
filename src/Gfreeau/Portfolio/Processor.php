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
     * @param array $portfolioConfig
     * @param array $rebalanceConfig
     * @return Portfolio
     */
    public function process(array $config, array $portfolioConfig, array $rebalanceConfig = null): Portfolio
    {
        if ($rebalanceConfig) {
            $portfolioConfig = $this->processRebalance($config, $portfolioConfig, $rebalanceConfig);
        }

        $processedAssetClasses = $this->processAssetClasses($portfolioConfig['assetClasses']);

        $accountData = $portfolioConfig['accounts'];

        $stockIds = [];

        foreach($accountData as $account) {
            $stockIds = array_merge($stockIds, array_keys($account['holdings']));
            unset($account);
        }

        $missingIds = array_diff($stockIds, array_keys($config['stocks']));

        if (count($missingIds) > 0) {
            throw new BadConfigurationException(sprintf('Missing data for stocks: %s', join(', ', $missingIds)));
        }

        $stockSymbols = array_map(function($id) use ($config) {
            return $config['stocks'][$id]['symbol'];
        }, $stockIds);

        $stockPrices = $this->getStockPrices($stockSymbols);

        $processedAccounts = [];

        foreach($accountData as $accountName => $account) {
            $processedHoldings = [];

            foreach($account['holdings'] as $holdingId => $quantity) {
                $holdingAssetClasses = [];

                $holding = $config['stocks'][$holdingId];

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
     * @param array $portfolioConfig
     * @param array $rebalanceConfig
     * @return array
     * @throws NotEnoughFundsException
     * @throws BadConfigurationException
     */
    protected function processRebalance(array $config, array $portfolioConfig, array $rebalanceConfig): array
    {
        $mainAccountListRef = &$portfolioConfig['accounts'];
        $rebalanceAccountList = $rebalanceConfig['accounts'];

        $stockIds = [];

        foreach($rebalanceAccountList as $account) {
            $stockIds = array_merge($stockIds, array_keys($account['buyHoldings']), $account['sellHoldings']);
            unset($account);
        }

        $missingIds = array_diff($stockIds, array_keys($config['stocks']));

        if (count($missingIds) > 0) {
            throw new BadConfigurationException(sprintf('Missing data for stocks: %s', join(', ', $missingIds)));
        }

        $stockSymbols = array_map(function($id) use ($config) {
            return $config['stocks'][$id]['symbol'];
        }, $stockIds);

        $stockPrices = $this->getStockPrices($stockSymbols);

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

                foreach($account['sellHoldings'] as $stockId) {
                    if (!isset($mainAccountRef['holdings'][$stockId])) {
                        throw new BadConfigurationException(sprintf('%s does not exist in account %s', $stockId, $accountName));
                    }

                    $quantity = $mainAccountRef['holdings'][$stockId];
                    $symbol = $config['stocks'][$stockId]['symbol'];

                    // todo support selling some shares but not all
                    $sellValue += $quantity * $stockPrices[$symbol];
                    $fees += $portfolioConfig['tradingFee'];

                    $holdingsToSell[] = $stockId;

                    unset($stockId, $quantity, $symbol);
                }

                $mainAccountRef['holdings'] = array_diff_key($mainAccountRef['holdings'], array_flip($holdingsToSell));
                $cashBalance += $sellValue;

                unset($currentHoldings, $holdingsToSell, $sellValue);
            }

            foreach($account['buyHoldings'] as $stockId => $quantity) {
                $cost += $quantity * $stockPrices[$config['stocks'][$stockId]['symbol']];
                $fees += $portfolioConfig['tradingFee'];

                unset($stockId, $quantity);
            }

            $cost += $fees;

            if ($cost > $cashBalance) {
                throw new NotEnoughFundsException(sprintf('The cost of $%4.2f exceeds the available cash of $%4.2f in the "%s" account', $cost, $cashBalance, $accountName));
            }

            $cashBalance -= $cost;

            $mainAccountRef['cash'] = $cashBalance;

            foreach($account['buyHoldings'] as $stockId => $quantity) {
                if (!isset($mainAccountRef['holdings'][$stockId])) {
                    $mainAccountRef['holdings'][$stockId] = 0;
                }

                $mainAccountRef['holdings'][$stockId] += $quantity;

                unset($stockId, $quantity);
            }
        }

        // we used references to overwrite config properties
        return $portfolioConfig;
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