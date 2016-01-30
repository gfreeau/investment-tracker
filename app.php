<?php

require 'vendor/autoload.php';

use Gfreeau\Portfolio\AssetClass;
use Gfreeau\Portfolio\Holding;
use Gfreeau\Portfolio\Portfolio;
use jc21\CliTable;
use jc21\CliTableManipulator;

function appError(string $message): void {
    echo trim($message) . '\n';
    exit(1);
}

$config = json_decode(file_get_contents('config/config.json'), true);

if (json_last_error() != JSON_ERROR_NONE) {
    appError('Cannot load config. Please check that the JSON is valid');
}

$processor = new \Gfreeau\Portfolio\Processor(
    new \Scheb\YahooFinanceApi\ApiClient()
);

$portfolio = $processor->process($config);

function showTotals(Portfolio $portfolio) {
    $data = [
        [
            'investment' => 'Cash',
            'amount'     => $portfolio->getCashValue(),
        ],
        [
            'investment' => 'Holdings',
            'amount'     => $portfolio->getHoldingsValue(),
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
            'currentAllocation' => $currentValue / $portfolio->getHoldingsValue() * 100,
            'currentValue'      => $currentValue,
        ];

        unset($assetClassHoldings);
    }

    $table = new CliTable;
    $table->setTableColor('blue');
    $table->setHeaderColor('cyan');
    $table->addField('Asset Class',        'name',              false                             , 'white');
    $table->addField('Target Allocation',  'targetAllocation',  new CliTableManipulator('percent'), 'white');
    $table->addField('Current Allocation', 'currentAllocation', new CliTableManipulator('percent'), 'white');
    $table->addField('Current Value',      'currentValue',      new CliTableManipulator('dollar'),  'white');
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
            'value'             => $holding->getValue(),
            'currentAllocation' => $holding->getValue() / $portfolio->getHoldingsValue() * 100,
        ];
    }

    $assetClassNames = array_flip(array_map(function(AssetClass $assetClass) {
        return $assetClass->getName();
    }, $portfolio->getAssetClasses()));

    usort($data, function($a, $b) use($assetClassNames) {
        return $assetClassNames[$a['assetClass']] <=> $assetClassNames[$b['assetClass']];
    });

    $table = new CliTable;
    $table->setTableColor('blue');
    $table->setHeaderColor('cyan');
    $table->addField('Holding',            'holding',           false,                              'white');
    $table->addField('Asset Class',        'assetClass',        false,                              'white');
    $table->addField('Quantity',           'quantity',          false,                              'white');
    $table->addField('Value',              'value',             new CliTableManipulator('dollar'),  'white');
    $table->addField('Current Allocation', 'currentAllocation', new CliTableManipulator('percent'), 'white');
    $table->injectData($data);
    $table->display();
}

showTotals($portfolio);
showAssetClasses($portfolio);
showAllHoldings($portfolio);