<?php

$queries = [
    sprintf('
        CREATE TABLE IF NOT EXISTS `%s` (
          `transaction_id` VARCHAR(255) NOT NULL PRIMARY KEY,
          `payment_method` VARCHAR(255) NOT NULL,
          `cart_id` INT(64),
          `order_id` INT(64),
          `status` VARCHAR(255) NOT NULL,
          `created_at` DATETIME NOT NULL,
          `updated_at` DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
        __DB_PREFIX_.'spryng_payments'
    )
];

foreach($queries as $query)
{
    if (!Db::getInstance()->execute($query))
    {
        $this->_errors[] = sprintf('Could not initialize database. Query: %s.', $query);
        return false;
    }
}