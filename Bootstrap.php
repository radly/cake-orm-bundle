<?php

namespace RadBundle\CakeORM;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;
use Rad\Config;
use Rad\Core\Bundle;

/**
 * Cake ORM Bootstrap
 *
 * @package RadBundle\CakeOrm
 */
class Bootstrap extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function startup()
    {
        parent::startup();

        Config::load(__DIR__ . DS . 'Resource' . DS . 'config' . DS . 'config.php');
        foreach (Config::get('CakeOrm.Datasources', []) as $name => $dataSource) {
            ConnectionManager::config($name, $dataSource);
        }

        Cache::config(Config::get('CakeOrm.Cache', []));
    }
}
