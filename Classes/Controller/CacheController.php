<?php
namespace Shel\Neos\NginxCache\Controller;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\Flow\Annotations as Flow;
use Neos\Error\Messages\Message;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\Http\Client\CurlEngineException;
use Neos\Flow\Http\Exception;
use Neos\Flow\Http\Uri;
use Neos\Flow\Http\Request;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\View\JsonView;
use Neos\FluidAdaptor\View\TemplateView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Domain\Service\ContentContextFactory;
use Neos\Neos\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\Neos\Domain\Service\NodeSearchService;
use Shel\Neos\NginxCache\Service\CacheFlushService;
use Shel\Neos\NginxCache\Service\ContentCacheFlusherService;

class CacheController extends AbstractModuleController
{
    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;
    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contextFactory;
    /**
     * @Flow\Inject
     * @var NodeSearchService
     */
    protected $nodeSearchService;
    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;
    /**
     * @Flow\Inject
     * @var ContentDimensionPresetSourceInterface
     */
    protected $contentDimensionPresetSource;

    /**
     * @var array
     */
    protected $viewFormatToObjectNameMap = [
        'html' => TemplateView::class,
        'json' => JsonView::class
    ];

    /**
     * @return void
     */
    public function indexAction()
    {
        $this->view->assign('activeSites', $this->siteRepository->findOnline());
    }

    /**
     * @param string $searchWord
     * @param Site $selectedSite
     * @return void
     * @throws NodeTypeNotFoundException
     */
    public function searchForNodeAction($searchWord, Site $selectedSite = null)
    {
        $documentNodeTypes = $this->nodeTypeManager->getSubNodeTypes('Neos.Neos:Document');
        $shortcutNodeType = $this->nodeTypeManager->getNodeType('Neos.Neos:Shortcut');
        $nodeTypes = array_diff($documentNodeTypes, [$shortcutNodeType]);
        $sites = [];
        $activeSites = $this->siteRepository->findOnline();
        foreach ($selectedSite ? [$selectedSite] : $activeSites as $site) {
            /** @var Site $site */
            $contextProperties = [
                'workspaceName' => 'live',
                'currentSite' => $site
            ];
            $contentDimensionPresets = $this->contentDimensionPresetSource->getAllPresets();
            if (count($contentDimensionPresets) > 0) {
                $mergedContentDimensions = [];
                foreach ($contentDimensionPresets as $contentDimensionIdentifier => $contentDimension) {
                    $mergedContentDimensions[$contentDimensionIdentifier] = [$contentDimension['default']];
                    foreach ($contentDimension['presets'] as $contentDimensionPreset) {
                        $mergedContentDimensions[$contentDimensionIdentifier] = array_merge($mergedContentDimensions[$contentDimensionIdentifier],
                            $contentDimensionPreset['values']);
                    }
                    $mergedContentDimensions[$contentDimensionIdentifier] = array_values(array_unique($mergedContentDimensions[$contentDimensionIdentifier]));
                }
                $contextProperties['dimensions'] = $mergedContentDimensions;
            }
            /** @var ContentContext $liveContext */
            $liveContext = $this->contextFactory->create($contextProperties);
            $nodes = $this->nodeSearchService->findByProperties($searchWord, $nodeTypes, $liveContext,
                $liveContext->getCurrentSiteNode());
            if (count($nodes) > 0) {
                $sites[$site->getNodeName()] = [
                    'site' => $site,
                    'nodes' => $nodes
                ];
            }
        }
        $this->view->assignMultiple([
            'searchWord' => $searchWord,
            'selectedSite' => $selectedSite,
            'sites' => $sites,
            'activeSites' => $activeSites
        ]);
    }

    /**
     * @param Node $node
     * @return void
     */
    public function purgeCacheAction(Node $node)
    {
        $service = new ContentCacheFlusherService();
        $service->flushForNode($node, $this->controllerContext);
        $this->view->assign('value', true);
    }

    /**
     * Purges the local cache if configured
     */
    public function purgeLocalCacheAction()
    {
        $service = new CacheFlushService();
        $result = $service->purgeLocalCache();
        if ($result) {
            $this->addFlashMessage('Local cache was purged.', 'Success', Message::SEVERITY_OK);
        } else {
            $this->addFlashMessage('Failed to purge local cache!', 'Error', Message::SEVERITY_ERROR);
        }
        $this->redirect('index');
    }

    /**
     * @param string $url
     * @return void
     * @throws CurlEngineException
     * @throws Exception
     */
    public function checkUrlAction($url)
    {
        $uri = new Uri($url);
        $request = Request::create($uri);
        $request = $request->withHeader('X-Cache-Debug', '1');
        $engine = new CurlEngine();
        $engine->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $engine->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $response = $engine->sendRequest($request);
        $this->view->assign('value', [
            'statusCode' => $response->getStatusCode(),
            'host' => parse_url($url, PHP_URL_HOST),
            'url' => $url,
            'headers' => array_map(function ($value) {
                return array_pop($value);
            }, $response->getHeaders()->getAll())
        ]);
    }
}
