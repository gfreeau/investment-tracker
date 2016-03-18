<?php

require 'vendor/autoload.php';

use Gfreeau\Portfolio\Holding;
use Gfreeau\Portfolio\Portfolio;
use jc21\CliTable;
use jc21\CliTableManipulator;
use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;

const CACHE_STOCK_PRICE_KEY = 'stock_prices';
const CACHE_STOCK_PRICE_TTL = 3600; // 1 hour

$getopt = new Getopt(array(
    (new Option('c', 'config', Getopt::REQUIRED_ARGUMENT))->setDefaultValue('config/main.yml'),
    (new Option('p', 'portfolio-config', Getopt::REQUIRED_ARGUMENT)),
    (new Option('r', 'rebalance-config', Getopt::REQUIRED_ARGUMENT)),
    (new Option('h', 'help')),
));

$getopt->parse();

if ($getopt->getOption('help')) {
    echo $getopt->getHelpText();
    exit(0);
}

function appError(string $message) {
    echo trim($message) . "\n";
    exit(1);
}

function getConfig($configFile, Symfony\Component\Config\Definition\ConfigurationInterface $configuration) {
    if (!file_exists($configFile)) {
        appError(sprintf('config file "%s" does not exist', $configFile));
    }

    $processor = new Symfony\Component\Config\Definition\Processor();

    return $processor->processConfiguration(
        $configuration,
        Symfony\Component\Yaml\Yaml::parse(file_get_contents($configFile))
    );
}

$config = getConfig(
    $getopt->getOption('config'),
    new \Gfreeau\Portfolio\Configuration\MainConfiguration()
);

$portfolioConfig = getConfig(
    $getopt->getOption('portfolio-config'),
    new \Gfreeau\Portfolio\Configuration\PortfolioConfiguration()
);

$stockPriceCacheKey = FileSystemCache::generateCacheKey(CACHE_STOCK_PRICE_KEY);
$stockPrices = FileSystemCache::retrieve($stockPriceCacheKey);
$shouldCacheStockPrices = false;

if($stockPrices === false) {
    $shouldCacheStockPrices = true;
    $stockPrices = [];
}

$stockPriceRetriever = new \Gfreeau\Portfolio\StockPriceRetriever(new \Scheb\YahooFinanceApi\ApiClient(), $stockPrices);

if ($shouldCacheStockPrices) {
    $allStockSymbols = array_map(function(array $stock) {
        return $stock['symbol'];
    }, $config['stocks']);

    $stockPrices = $stockPriceRetriever->getStockPrices($allStockSymbols); // warm up

    FileSystemCache::store($stockPriceCacheKey, $stockPrices, CACHE_STOCK_PRICE_TTL);
}

$processor = new \Gfreeau\Portfolio\Processor($stockPriceRetriever);

$rebalanceConfig = null;

if ($getopt->getOption('rebalance-config')) {
    $rebalanceConfig = getConfig(
        $getopt->getOption('rebalance-config'),
        new \Gfreeau\Portfolio\Configuration\RebalanceConfiguration()
    );
}

try {
    $portfolio = $processor->process($config, $portfolioConfig, $rebalanceConfig);
} catch (\Gfreeau\Portfolio\Exception\NotEnoughFundsException $e) {
    appError($e->getMessage());
}

function getTitle($title) {
    return chr(27).'[0;37m' . trim($title) . "\n" . chr(27).'[0m';
}

function showTotals(Portfolio $portfolio) {
    $data = [
        [
            'investment' => 'Holdings',
            'amount'     => $portfolio->getHoldingsValue(),
        ],
        [
            'investment' => 'Cash',
            'amount'     => $portfolio->getCashValue(),
        ],
        [
            'investment' => 'Total',
            'amount'     => $portfolio->getTotalValue(),
        ]
    ];

    $table = new CliTable;
    $table->setTableColor('blue');
    $table->setHeaderColor('cyan');
    $table->addField('Investment', 'investment', false,                             'white');
    $table->addField('Amount',     'amount',     new CliTableManipulator('dollar'), 'white');
    $table->injectData($data);
    $table->display();
}

function showAssetClasses(Portfolio $portfolio) {
    $assetClasses = $portfolio->getAssetClasses();

    $holdings = $portfolio->getAllHoldings();

    $data = [];

    foreach($assetClasses as $assetClass) {
        $currentValue = array_reduce($holdings, function($value, Holding $holding) use($assetClass) {
            return $value + $holding->getAssetClassValue($assetClass);
        }, 0);

        $data[] = [
            'name'              => $assetClass->getName(),
            'targetAllocation'  => $assetClass->getTargetAllocation(),
            'currentAllocation' => $currentValue / $portfolio->getHoldingsValue(),
            'currentValue'      => $currentValue,
        ];
    }

    $table = new CliTable;
    $table->setTableColor('blue');
    $table->setHeaderColor('cyan');
    $table->addField('Asset Class',        'name',              false,                                                 'white');
    $table->addField('Target Allocation',  'targetAllocation',  new \Gfreeau\Portfolio\CliTableManipulator('percent'), 'white');
    $table->addField('Current Allocation', 'currentAllocation', new \Gfreeau\Portfolio\CliTableManipulator('percent'), 'white');
    $table->addField('Current Value',      'currentValue',      new CliTableManipulator('dollar'),                     'white');
    $table->injectData($data);
    $table->display();
}

function showAccounts(Portfolio $portfolio) {
    $accounts = $portfolio->getAccounts();

    $data = [];

    foreach($accounts as $account) {
        $data[] = [
            'name'          => $account->getName(),
            'holdingsValue' => $account->getHoldingsValue(),
            'cashValue'     => $account->getCashValue(),
            'totalValue'    => $account->getAccountValue(),
        ];
    }

    $table = new CliTable;
    $table->setTableColor('blue');
    $table->setHeaderColor('cyan');
    $table->addField('Account',        'name',          false                            , 'white');
    $table->addField('Holdings Value', 'holdingsValue', new CliTableManipulator('dollar'), 'white');
    $table->addField('Cash Value',     'cashValue',     new CliTableManipulator('dollar'), 'white');
    $table->addField('Total Value',    'totalValue',    new CliTableManipulator('dollar'), 'white');
    $table->injectData($data);
    $table->display();
}

function showAllHoldings(Portfolio $portfolio) {
    $data = [];

    $accounts = $portfolio->getAccounts();

    foreach ($accounts as $account) {
        foreach ($account->getHoldings() as $holding) {
            $data[] = [
                'account'           => $account->getName(),
                'holding'           => $holding->getName(),
                'symbol'            => $holding->getSymbol(),
                'quantity'          => $holding->getQuantity(),
                'price'             => $holding->getPrice(),
                'value'             => $holding->getValue(),
                'currentAllocation' => $holding->getValue() / $portfolio->getHoldingsValue(),
            ];
        }
    }

    $accountNames = array_flip(array_map(function(\Gfreeau\Portfolio\Account $account) {
        return $account->getName();
    }, $accounts));

    // order by account as defined in the config and then by price
    usort($data, function($a, $b) use($accountNames) {
        $orderByAccount = $accountNames[$a['account']] <=> $accountNames[$b['account']];
        if ($orderByAccount !== 0) {
            return $orderByAccount;
        }
        // asset class is the same, compare price
        return $b['value'] <=> $a['value'];
    });

    $table = new CliTable;
    $table->setTableColor('blue');
    $table->setHeaderColor('cyan');
    $table->addField('Account',            'account',           false,                                                 'white');
    $table->addField('Holding',            'holding',           false,                                                 'white');
    $table->addField('Symbol',             'symbol',            false,                                                 'white');
    $table->addField('Quantity',           'quantity',          false,                                                 'white');
    $table->addField('Price',              'price',             new CliTableManipulator('dollar'),                     'white');
    $table->addField('Value',              'value',             new CliTableManipulator('dollar'),                     'white');
    $table->addField('Current Allocation', 'currentAllocation', new \Gfreeau\Portfolio\CliTableManipulator('percent'), 'white');
    $table->injectData($data);
    $table->display();
}

function whatCouldIBuy(Portfolio $portfolio) {
    echo getTitle("The table below shows how many shares can be purchased with available cash");

    $data = [];

    $accounts = $portfolio->getAccounts();

    foreach ($portfolio->getAllHoldings() as $holding) {
        $row = [
            'symbol'            => $holding->getSymbol(),
        ];

        foreach($accounts as $account) {
            $row[$account->getName()] = floor($account->getCashValue() / $holding->getPrice());
        }

        $data[] = $row;
        unset($row);
    }

    $table = new CliTable;
    $table->setTableColor('blue');
    $table->setHeaderColor('cyan');
    $table->addField('Symbol', 'symbol', false, 'white');
    foreach($accounts as $account) {
        $table->addField($account->getName(), $account->getName(), false, 'white');
    }
    $table->injectData($data);
    $table->display();
}

showTotals($portfolio);
showAccounts($portfolio);
whatCouldIBuy($portfolio);
showAssetClasses($portfolio);
showAllHoldings($portfolio);