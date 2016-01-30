<?php

namespace Gfreeau\Portfolio;

use jc21\CliTableManipulator as BaseCliTableManipulator;

class CliTableManipulator extends BaseCliTableManipulator
{
    public function percent($value)
    {
        return sprintf('%4.2f', $value * 100) . '%';
    }
}