<?php
namespace Shel\Neos\NginxCache\Service;

use FOS\HttpCache\CacheInvalidator;
use FOS\HttpCache\Exception\ExceptionCollection;
use FOS\HttpCache\Exception\ProxyResponseException;
use FOS\HttpCache\Exception\ProxyUnreachableException;
use FOS\HttpCache\ProxyClient;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\PluginClient;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;
use Neos\Utility\Exception\FilesException;
use Neos\Utility\Files;
use Psr\Log\LoggerInterface;
use Http\Adapter\Guzzle6\Client as Guzzle6;

/**
 * @Flow\Scope("singleton")
 */
class CacheFlushService
{

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
     * @var ProxyClient\Nginx
     */
    protected $nginxProxyClient;

    /**
     * @var CacheInvalidator
     */
    protected $cacheInvalidator;

    /**
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return void
     */
    public function initializeObject()
    {
        $servers = is_array($this->settings['servers']) ? $this->settings['servers'] : [$this->settings['servers'] ?: '127.0.0.1'];
        $baseUri = $this->settings['baseUri'] ?: '';

        $client = Guzzle6::createWithConfig([
            'base_uri' => $baseUri,
            'verify' => false
        ]);

        $httpClient = new PluginClient(
            $client,
            [new ErrorPlugin()]
        );

        $httpDispatcher = new ProxyClient\HttpDispatcher($servers, $baseUri, $httpClient);
        $options = [];

        $this->nginxProxyClient = new ProxyClient\Nginx($httpDispatcher, $options);
        $this->cacheInvalidator = new CacheInvalidator($this->nginxProxyClient);
    }

    /**
     * @param $path
     */
    public function refreshPath($path)
    {
        $this->logger->info('Refreshing path ' . $path);
        $this->cacheInvalidator->refreshPath($path);
        $this->execute();
    }

    /**
     * @param $path
     */
    public function invalidatePath($path)
    {
        if ($this->settings['purge']['installed']) {
            $this->logger->info('Invalidating path ' . $path);
            $this->cacheInvalidator->invalidatePath($path);
            $this->execute();
        } else {
            $this->refreshPath($path);
        }
    }

    /**
     * @return void
     */
    protected function execute()
    {
        try {
            $this->cacheInvalidator->flush();
        } catch (ExceptionCollection $exceptions) {
            foreach ($exceptions as $exception) {
                if ($exception instanceof ProxyResponseException) {
                    $this->logger->error(sprintf('Error calling nginx with request (cannot connect to the caching proxy). Error %s',
                        $exception->getMessage()));
                } elseif ($exception instanceof ProxyUnreachableException) {
                    $this->logger->error(sprintf('Error calling nginx with request (caching proxy returned an error response). Error %s',
                        $exception->getMessage()));
                } else {
                    $this->logger->error(sprintf('Error calling nginx with request. Error %s',
                        $exception->getMessage()));
                }
            }
        }
    }

    /**
     * Deletes the configured cache folder.
     * Don't use this if you have multiple NGINX instances or NGINX is not on the same server as your application.
     * The configured directly must be writable by the user running the application.
     */
    public function purgeLocalCache(): bool
    {
        if ($this->settings['localCachePath']) {
            try {
                Files::emptyDirectoryRecursively($this->settings['localCachePath']);
                $this->logger->info('Purged local cache');
                return true;
            } catch (FilesException $e) {
                $this->logger->error('Could not purge local cache', [$e->getMessage()]);
            }
        } else {
            $this->logger->error('Could not purge local cache as it\'s path is not configured');
        }
        return false;
    }
}
