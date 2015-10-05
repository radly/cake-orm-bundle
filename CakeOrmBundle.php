<?php

namespace CakeOrm;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
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

        if (!is_dir($cakeLogPath = LOG_DIR . DS . 'cake')) {
            mkdir($cakeLogPath, 0777, true);
        }

        Log::config(
            'queries',
            [
                'className' => 'File',
                'path' => LOG_DIR . DS . 'cake' . DS,
                'file' => 'queries.log',
                'scopes' => ['queriesLog']
            ]
        );

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
