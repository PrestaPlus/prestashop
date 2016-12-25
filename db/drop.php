<?php

$queries = [
    'DROP TABLE IF EXISTS `'._DB_PREFIX_.'spryng_payments_transactions`',
    'DROP TABLE IF EXISTS `'._DB_PREFIX_.'spryng_payments_logs`'
];

foreach ($queries as $query)
{
    if (!Db::getInstance()->execute($query))
    {
        return false;
    }
}