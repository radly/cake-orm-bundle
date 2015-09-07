<?php

namespace CakeOrm;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;
use CakeOrm\Event\CakeORMSubscriber;
use Rad\Configure\Config;
use Rad\Core\AbstractBundle;

/**
 * Cake ORM Bootstrap
 *
 * @package RadBundle\CakeOrm
 */
class CakeOrmBundle extends AbstractBundle
{
    /**
     * {@inheritdoc}
     */
    public function startup()
    {
        foreach (Config::get('cake_orm.datasources', []) as $name => $dataSource) {
            ConnectionManager::config($name, $dataSource);
        }

        Cache::config(Config::get('cake_orm.cache', []));

        $this->getEventManager()->addSubscriber(new CakeORMSubscriber());
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfig()
    {
        Config::load(__DIR__ . DS . 'Resource' . DS . 'config' . DS . 'config.php');
    }
}
