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
     * Clear all NGINX cache entries on the local system.
     *
     * @return void
     */
    public function purgeLocalCommand()
    {
        $result = $this->cacheFlushService->purgeLocalCache();

        if ($result) {
            $this->outputLine('Local NGINX cache was cleared');
        } else {
            $this->outputLine('Local NGINX cache was not cleared, please check the logs');
        }
    }

    /**
     * @param string $nodeIdentifier
     */
    public function flushNodeCommand($nodeIdentifier)
    {
        $context = $this->createContentContext('live');
        $node = $context->getNodeByIdentifier($nodeIdentifier);

        $controllerContext = $this->contextBuildService->buildControllerContext();

        $nodeUri = '';

        try {
            $nodeUri = $this->linkingService->createNodeUri(
                $controllerContext,
                $node,
                null,
                'html',
                true
            );
        } catch (\Exception $e) {
            $this->outputLine($e->getMessage());
        }

        if ($nodeUri) {
            $this->outputLine(sprintf('Invalidating %s', $nodeUri));
            $this->cacheFlushService->invalidatePath($nodeUri);
        } else {
            $this->outputLine(sprintf('Failed generating the path for node with identifier %s', $nodeIdentifier));
        }
    }
}
