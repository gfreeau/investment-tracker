<?php

namespace Gfreeau\Portfolio;

use Scheb\YahooFinanceApi\ApiClient;

class StockPriceRetriever
{
    /**
     * @var ApiClient
     */
    private $financeClient;
    /**
     * @var array
     */
    private $stockPrices;

    public function __construct(ApiClient $financeClient, array $stockPrices = [])
    {
        $this->financeClient = $financeClient;
        $this->stockPrices = $stockPrices;
    }

    public function getStockPrices(array $symbols): array {
        $symbols = array_unique($symbols);

        $missingSymbols = array_diff($symbols, array_keys($this->stockPrices));

        if (!empty($missingSymbols)) {
            $this->stockPrices = array_merge($this->stockPrices, $this->queryStockData($missingSymbols));
        }

        return array_intersect_key($this->stockPrices, array_flip($symbols));
    }

    protected function queryStockData(array $symbols): array
    {
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
            return (double) $stockData['LastTradePriceOnly'];
        }, $data);

        return array_combine($keys, $values);
    }

    protected function getStockData(array $symbols): array {
        return $this->financeClient->getQuotesList($symbols);
    }
}