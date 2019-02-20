<?php
namespace Shel\Neos\NginxCache\Service;

use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ControllerInterface;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Mvc\RequestInterface;
use Neos\Flow\Mvc\ResponseInterface;
use Neos\Neos\Controller\Frontend\NodeController;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Psr\Log\LoggerInterface;
use Shel\Neos\NginxCache\Aspects\ContentCacheAspect;
use Shel\Neos\NginxCache\Cache\MetadataAwareStringFrontend;

/**
 * Service for adding cache headers to a to-be-sent response
 *
 * @Flow\Scope("singleton")
 */
class CacheControlService
{

    /**
     * @var ContentCacheAspect
     * @Flow\Inject
     */
    protected $contentCacheAspect;

    /**
     * @var MetadataAwareStringFrontend
     * @Flow\Inject
     */
    protected $contentCacheFrontend;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Adds cache headers to the response.
     *
     * Called via a signal triggered by the MVC Dispatcher
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param ControllerInterface $controller
     * @return void
     * @throws NodeException
     * @throws NoSuchArgumentException
     */
    public function addHeaders(RequestInterface $request, ResponseInterface $response, ControllerInterface $controller)
    {
        if (isset($this->settings['cacheHeaders']['disabled']) && $this->settings['cacheHeaders']['disabled'] === true) {
            $this->logger->debug('NGINX cache headers disabled (see configuration setting Shel.Neos.NginxCache.cacheHeaders.disabled)');
            return;
        }
        if (!$response instanceof ResponseInterface || !$controller instanceof NodeController) {
            return;
        }
        $arguments = $controller->getControllerContext()->getArguments();
        if (!$arguments->hasArgument('node')) {
            return;
        }
        $node = $arguments->getArgument('node')->getValue();
        if (!$node instanceof NodeInterface) {
            return;
        }
        if ($node->getContext()->getWorkspaceName() !== 'live') {
            return;
        }
        if ($node->hasProperty('disableNginxCache') && $node->getProperty('disableNginxCache') === true) {
            $this->logger->debug(sprintf('NGINX cache disabled due to property "disableNginxCache" for node "%s" (%s)', $node->getLabel(), $node->getPath()));
            $response->getHeaders()->setCacheControlDirective('no-cache');
            $response->setHeader('X-Accel-Expires', 0);
            return;
        }

        if ($this->contentCacheAspect->isEvaluatedUncached()) {
            $this->logger->debug(sprintf('NGINX cache disabled due to uncachable content for node "%s" (%s)', $node->getLabel(), $node->getPath()));
            $response->getHeaders()->setCacheControlDirective('no-cache');
            $response->setHeader('X-Accel-Expires', 0);
        } else {
            $cacheLifetime = $this->getCacheLifetime();

            $nodeLifetime = $node->getProperty('cacheTimeToLive');
            if ($nodeLifetime === '' || $nodeLifetime === null) {
                $defaultLifetime = isset($this->settings['cacheHeaders']['defaultSharedMaximumAge']) ? $this->settings['cacheHeaders']['defaultSharedMaximumAge'] : null;
                $timeToLive = $defaultLifetime;
                if ($defaultLifetime === null) {
                    $timeToLive = $cacheLifetime;
                } elseif ($cacheLifetime !== null) {
                    $timeToLive = min($defaultLifetime, $cacheLifetime);
                }
            } else {
                $timeToLive = $nodeLifetime;
            }

            if ($timeToLive !== null) {
                $response->setMaximumAge(intval($timeToLive));
                $response->setHeader('X-Accel-Expires', intval($timeToLive));
                $this->logger->debug(sprintf('NGINX cache enabled for node "%s" (%s) with max-age "%u"', $node->getLabel(), $node->getPath(), $timeToLive));
            } else {
                $this->logger->debug(sprintf('NGINX cache headers not sent for node "%s" (%s) due to no max-age', $node->getLabel(), $node->getPath()));
            }
        }
    }

    /**
     * Get cache tags and lifetime from the cache metadata that was extracted by the special cache frontend
     */
    protected function getCacheLifetime(): ?int
    {
        $lifetime = null;
        $entriesMetadata = $this->contentCacheFrontend->getAllMetadata();
        foreach ($entriesMetadata as $identifier => $metadata) {
            $entryLifetime = isset($metadata['lifetime']) ? $metadata['lifetime'] : null;

            if ($entryLifetime !== null) {
                if ($lifetime === null) {
                    $lifetime = $entryLifetime;
                } else {
                    $lifetime = min($lifetime, $entryLifetime);
                }
            }
        }
        return $lifetime;
    }
}
