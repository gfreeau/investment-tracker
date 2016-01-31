<?php

require 'vendor/autoload.php';

use Gfreeau\Portfolio\AssetClass;
use Gfreeau\Portfolio\Holding;
use Gfreeau\Portfolio\Portfolio;
use jc21\CliTable;
use jc21\CliTableManipulator;
use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;

$getopt = new Getopt(array(
    (new Option('c', 'config', Getopt::REQUIRED_ARGUMENT))->setDefaultValue('config/config.json'),
    (new Option(null, 'contribution-config', Getopt::REQUIRED_ARGUMENT)),
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

function getConfig($configFile) {
    if (!file_exists($configFile)) {
        appError(sprintf('config file "%s" does not exist', $configFile));
    }

    $config = json_decode(file_get_contents($configFile), true);

    if (json_last_error() != JSON_ERROR_NONE) {
        appError('Cannot load config. Please check that the JSON is valid');
    }

    return $config;
}

$config = getConfig($getopt->getOption('config'));

$processor = new \Gfreeau\Portfolio\Processor(
    new \Scheb\YahooFinanceApi\ApiClient()
);

if ($getopt->getOption('contribution-config')) {
    $contributionConfig = getConfig($getopt->getOption('contribution-config'));

    try {
        $portfolio = $processor->process($config, $contributionConfig);
    } catch (\Gfreeau\Portfolio\Exception\ContributionExceededException $e) {
        appError($e->getMessage());
    }
} else {
    $portfolio = $processor->process($config);
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
        $assetClassHoldings = array_filter($holdings, function(Holding $holding) use($assetClass) {
            return $holding->getAssetClass() === $assetClass;
        });

        $currentValue = array_reduce($assetClassHoldings, function($value, Holding $holding) {
            return $value + $holding->getValue();
        }, 0);

        $data[] = [
            'name'              => $assetClass->getName(),
            'targetAllocation'  => $assetClass->getTargetAllocation() * 100,
            'currentAllocation' => $currentValue / $portfolio->getHoldingsValue(),
            'currentValue'      => $currentValue,
        ];

        unset($assetClassHoldings);
    }

    $table = new CliTable;
    $table->setTableColor('blue');
    $table->setHeaderColor('cyan');
    $table->addField('Asset Class',        'name',              false,                                                 'white');
    $table->addField('Target Allocation',  'targetAllocation',  new CliTableManipulator('percent'),                    'white');
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

    foreach ($portfolio->getAllHoldings() as $holding) {
        $data[] = [
            'holding'           => $holding->getName(),
            'assetClass'        => $holding->getAssetClass()->getName(),
            'quantity'          => $holding->getQuantity(),
            'price'             => $holding->getPrice(),
            'value'             => $holding->getValue(),
            'currentAllocation' => $holding->getValue() / $portfolio->getHoldingsValue(),
        ];
    }

    $assetClassNames = array_flip(array_map(function(AssetClass $assetClass) {
        return $assetClass->getName();
    }, $portfolio->getAssetClasses()));

    // order by asset class as defined in the config and then by price
    usort($data, function($a, $b) use($assetClassNames) {
        $orderByAssetClass = $assetClassNames[$a['assetClass']] <=> $assetClassNames[$b['assetClass']];

        if ($orderByAssetClass !== 0) {
            return $orderByAssetClass;
        }

        // asset class is the same, compare price
        return $b['value'] <=> $a['value'];
    });

    $table = new CliTable;
    $table->setTableColor('blue');
    $table->setHeaderColor('cyan');
    $table->addField('Holding',            'holding',           false,                                                 'white');
    $table->addField('Asset Class',        'assetClass',        false,                                                 'white');
    $table->addField('Quantity',           'quantity',          false,                                                 'white');
    $table->addField('Price',              'price',             new CliTableManipulator('dollar'),                     'white');
    $table->addField('Value',              'value',             new CliTableManipulator('dollar'),                     'white');
    $table->addField('Current Allocation', 'currentAllocation', new \Gfreeau\Portfolio\CliTableManipulator('percent'), 'white');
    $table->injectData($data);
    $table->display();
}

showTotals($portfolio);
showAssetClasses($portfolio);
showAccounts($portfolio);
showAllHoldings($portfolio);