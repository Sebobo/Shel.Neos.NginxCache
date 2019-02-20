<?php
namespace Shel\Neos\NginxCache\Aspects;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Utility\Exception\PropertyNotAccessibleException;
use Neos\Utility\ObjectAccess;
use Neos\Fusion\Core\Runtime;
use Psr\Log\LoggerInterface;

/**
 * Advice the RuntimeContentCache to check for uncached segments that should prevent caching
 *
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class ContentCacheAspect
{

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var boolean
     */
    protected $evaluatedUncached;

    /**
     * Advice for uncached segments when rendering the initial output (without replacing an uncached marker in cached output)
     *
     * @Flow\AfterReturning("setting(Shel.Neos.NginxCache.enabled) && method(Neos\Fusion\Core\Cache\RuntimeContentCache->postProcess())")
     * @param JoinPointInterface $joinPoint
     * @throws PropertyNotAccessibleException
     */
    public function registerCreateUncached(JoinPointInterface $joinPoint)
    {
        $evaluateContext = $joinPoint->getMethodArgument('evaluateContext');

        $proxy = $joinPoint->getProxy();
        /** @var Runtime $runtime */
        $runtime = ObjectAccess::getProperty($proxy, 'runtime', true);

        if ($evaluateContext['cacheForPathDisabled']) {
            $nginxCacheIgnoreUncached = $runtime->evaluate($evaluateContext['fusionPath'] . '/__meta/cache/nginxCacheIgnoreUncached');
            if ($nginxCacheIgnoreUncached !== true) {
                $this->logger->log(sprintf('NGINX cache disabled due to uncached path "%s" (can be prevented using "nginxCacheIgnoreUncached")', $evaluateContext['fusionPath']), LOG_DEBUG);
                $this->evaluatedUncached = true;
            }
        }
    }

    /**
     * Advice for uncached segments when rendering from a cached version
     *
     * @Flow\AfterReturning("setting(Shel.Neos.NginxCache.enabled) && method(Neos\Fusion\Core\Cache\RuntimeContentCache->evaluateUncached())")
     * @param JoinPointInterface $joinPoint
     * @throws PropertyNotAccessibleException
     */
    public function registerEvaluateUncached(JoinPointInterface $joinPoint)
    {
        $path = $joinPoint->getMethodArgument('path');

        $proxy = $joinPoint->getProxy();
        /** @var Runtime $runtime */
        $runtime = ObjectAccess::getProperty($proxy, 'runtime', true);

        $nginxCacheIgnoreUncached = $runtime->evaluate($path . '/__meta/cache/nginxCacheIgnoreUncached');
        if ($nginxCacheIgnoreUncached !== true) {
            $this->logger->log(sprintf('NGINX cache disabled due to uncached path "%s" (can be prevented using "nginxCacheIgnoreUncached")', $path . '/__meta/cache/nginxCacheIgnoreUncached'), LOG_DEBUG);
            $this->evaluatedUncached = true;
        }
    }

    /**
     * Advice for a disabled content cache (e.g. because an exception was handled)
     *
     * @Flow\AfterReturning("setting(Shel.Neos.NginxCache.enabled) && method(Neos\Fusion\Core\Cache\RuntimeContentCache->setEnableContentCache())")
     * @param JoinPointInterface $joinPoint
     */
    public function registerDisableContentCache(JoinPointInterface $joinPoint)
    {
        $enableContentCache = $joinPoint->getMethodArgument('enableContentCache');
        if ($enableContentCache !== true) {
            $this->logger->debug('NGINX cache disabled due content cache being disabled (e.g. because an exception was handled)');
            $this->evaluatedUncached = true;
        }
    }

    /**
     * @return boolean TRUE if an uncached segment was evaluated
     */
    public function isEvaluatedUncached()
    {
        return $this->evaluatedUncached;
    }
}
