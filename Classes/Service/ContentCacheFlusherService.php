<?php
namespace Shel\Neos\NginxCache\Service;

use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Neos\Controller\CreateContentContextTrait;
use Neos\Neos\Service\LinkingService;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class ContentCacheFlusherService
{
    use CreateContentContextTrait;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var CacheFlushService
     */
    protected $cacheFlushService;

    /**
     * @var array
     */
    protected $pathsToInvalidate = [];

    /**
     * @var array
     */
    protected $pathsToRefresh = [];

    /**
     * @Flow\Inject
     * @var LinkingService
     */
    protected $linkingService;

    /**
     * @Flow\Inject
     * @var ContextBuilderService
     */
    protected $contextBuildService;

    /**
     * Flushes the public cache of the node if the public workspace was affected
     *
     * @param NodeInterface $node The node which has changed in some way
     * @param Workspace|null $workspace
     * @return void
     */
    public function flushPublishedNode(NodeInterface $node, Workspace $workspace)
    {
        if ($workspace->isPublicWorkspace()) {
            $controllerContext = $this->contextBuildService->buildControllerContext();
            $this->flushForNode($node, $controllerContext);
        }
    }

    /**
     * @param NodeInterface $node The node which has changed in some way
     * @param ControllerContext $controllerContext
     * @return void
     */
    public function flushForNode(NodeInterface $node, ControllerContext $controllerContext = null)
    {
        $this->generateCachePaths($node, $controllerContext);
    }

    /**
     * Generates url paths to be flushed for a node which is flushed on shutdown.
     *
     * @param NodeInterface|NodeData $node The node which has changed in some way
     * @param ControllerContext $controllerContext
     * @return void
     */
    protected function generateCachePaths($node, ControllerContext $controllerContext)
    {
        $this->generateCachePathsForNode($node, $controllerContext);
    }

    /**
     * @param NodeInterface $node
     * @param ControllerContext $controllerContext
     * @return void
     */
    protected function generateCachePathsForNode(NodeInterface $node, ControllerContext $controllerContext)
    {
        $nodeUri = '';
        $format = 'html';

        $documentNode = $this->getClosestPublicDocumentNode($node);

        if (!$documentNode) {
            $this->logger->info('Skipped unreachable node from cache refresh ' . $documentNode->getIdentifier());
            return;
        }

        try {
            $this->logger->debug('Building url for ' . $documentNode->getLabel());

            $nodeUri = $this->linkingService->createNodeUri(
                $controllerContext,
                $documentNode,
                null,
                $format,
                true
            );
        } catch (MissingActionNameException $e) {
            $this->logger->error($e->getMessage());
        } catch (\Neos\Flow\Security\Exception $e) {
            $this->logger->error($e->getMessage());
        } catch (\Neos\Flow\Property\Exception $e) {
            $this->logger->error($e->getMessage());
        } catch (\Neos\Neos\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        if ($nodeUri) {

            if ($node->isRemoved() || $node->isHidden()) {
                $this->logger->debug('Path added for invalidation ' . $nodeUri);
                $this->pathsToInvalidate[$nodeUri] = sprintf('Node %s is not accessible anymore', $node->getLabel());
            } else {
                $this->logger->debug('Path added for refreshing ' . $nodeUri);
                $this->pathsToRefresh[$nodeUri] = sprintf('Node %s was changed', $node->getLabel());
            }
        }
    }

    /**
     * Retrieves the closest document node for the given node which is available in a public workspace
     *
     * @param NodeInterface $node
     * @return NodeInterface
     */
    protected function getClosestPublicDocumentNode(NodeInterface $node): NodeInterface
    {
        $documentNode = $node;
        while ($documentNode && !$documentNode->getNodeType()->isOfType('Neos.Neos:Document')) {
            $documentNode = $documentNode->getParent();
        }

        $context = $this->createContentContext('live', $documentNode->getDimensions());
        $publicNode = $context->getNodeByIdentifier($documentNode->getIdentifier());

        return $publicNode;
    }

    /**
     * Flush caches according to the previously registered node changes.
     *
     * @return void
     */
    public function shutdownObject()
    {
        foreach (array_keys($this->pathsToInvalidate) as $path) {
            $this->cacheFlushService->invalidatePath($path);
        }
        foreach (array_keys($this->pathsToRefresh) as $path) {
            $this->cacheFlushService->refreshPath($path);
        }
    }
}
