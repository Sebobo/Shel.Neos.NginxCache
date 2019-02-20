<?php
namespace Shel\Neos\NginxCache;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\Dispatcher;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Neos\Service\PublishingService;
use Shel\Neos\NginxCache\Service\CacheControlService;
use Shel\Neos\NginxCache\Service\ContentCacheFlusherService;

class Package extends BasePackage
{

    /**
     * Register slots for sending correct headers and BANS to nginx
     *
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $dispatcher->connect(ConfigurationManager::class, 'configurationManagerReady', function (ConfigurationManager $configurationManager) use ($dispatcher) {
            $enabled = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Shel.Neos.NginxCache.enabled');
            if (!!$enabled) {
                $dispatcher->connect(PublishingService::class, 'nodePublished', ContentCacheFlusherService::class, 'flushPublishedNode');
                $dispatcher->connect(Dispatcher::class, 'afterControllerInvocation', CacheControlService::class, 'addHeaders');
            }
        });
    }
}
