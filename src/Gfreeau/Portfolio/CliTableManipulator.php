<?php

namespace Gfreeau\Portfolio;

use jc21\CliTableManipulator as BaseCliTableManipulator;

class CliTableManipulator extends BaseCliTableManipulator
{
    public function percent($value)
    {
        $value *= 100;

        if ($value == intval($value)) {
            // no need to show the .00
            return $value . '%';
        }

        return sprintf('%4.2f', $value) . '%';
    }

    public function DollarDiff($value)
    {
        $sign = '';

        if ($value > 0) {
            $sign = '+';
        } else if ($value < 0) {
            $sign = '-';
        }

        $value = number_format(abs($value), 2);
        $value = $sign . '$' . $value;

        return $value;
    }
}