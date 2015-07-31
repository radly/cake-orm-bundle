<?php

namespace CakeOrm;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;
use CakeOrm\Event\CakeORMSubscriber;
use Rad\Configure\Config;
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
        foreach (Config::get('cake_orm.datasources', []) as $name => $dataSource) {
            ConnectionManager::config($name, $dataSource);
        }

        Cache::config(Config::get('cake_orm.cache', []));

        $this->getEventManager()->addSubscriber(new CakeORMSubscriber());
    }
}
