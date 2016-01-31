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
}