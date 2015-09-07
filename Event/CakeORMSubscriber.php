<?php

namespace CakeOrm\Event;

use Application;
use Rad\Core\Bundles;
use Rad\Events\Event;
use Rad\Events\EventManager;
use Rad\Events\EventSubscriberInterface;

/**
 * Cake ORM Subscriber
 *
 * @package RadBundle\CakeORM\Event
 */
class CakeORMSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public function subscribe(EventManager $eventManager)
    {
        $eventManager->attach(Application::EVENT_AFTER_BUNDLE_STARTUP, [$this, 'loadTableRegistryMap']);
    }

    /**
     * Load table registry map
     *
     * @param Event $event
     *
     * @throws \Rad\Core\Exception\MissingBundleException
     */
    public function loadTableRegistryMap(Event $event)
    {
        foreach (Bundles::getLoaded() as $bundleName) {
            $mapDir = Bundles::getPath($bundleName) . DS . 'Domain' . DS . 'map';

            if (is_file($mapDir . DS . 'table_registry_config.php')) {
                require_once $mapDir . DS . 'table_registry_config.php';
            }
        }
    }
}
