<?php
namespace Shel\Neos\NginxCache\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Controller\CreateContentContextTrait;
use Neos\Neos\Service\LinkingService;
use Shel\Neos\NginxCache\Service\CacheFlushService;
use Shel\Neos\NginxCache\Service\ContextBuilderService;

/**
 * @Flow\Scope("singleton")
 */
class NginxCacheCommandController extends \Neos\Flow\Cli\CommandController
{
    use CreateContentContextTrait;

    /**
     * @var CacheFlushService
     * @Flow\Inject
     */
    protected $cacheFlushService;

    /**
     * @Flow\Inject
     * @var ContextBuilderService
     */
    protected $contextBuildService;

    /**
     * @Flow\Inject
     * @var LinkingService
     */
    protected $linkingService;

    /**
     * Clear all cache in NGINX for a optionally given domain
     *
     * @param string $domain The domain to flush, e.g. "example.com"
     * @return void
     */
    public function clearCommand($domain = null)
    {
        $this->cacheFlushService->invalidateAll($domain);
    }

    /**
     * @param string $nodeIdentifier
     */
    public function flushNodeCommand($nodeIdentifier)
    {
        $context = $this->createContentContext('live');
        $node = $context->getNodeByIdentifier($nodeIdentifier);

        $controllerContext = $this->contextBuildService->buildControllerContext();
        $nodeUri = $this->linkingService->createNodeUri(
            $controllerContext,
            $node,
            null,
            'html',
            true
        );

        $this->outputLine($nodeUri);
    }
}
