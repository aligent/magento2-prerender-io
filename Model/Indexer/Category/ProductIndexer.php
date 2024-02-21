<?php
/*
 * Copyright (c) Aligent Consulting. All rights reserved.
 */

declare(strict_types=1);

namespace Aligent\PrerenderIo\Model\Indexer\Category;

use Aligent\PrerenderIo\Api\PrerenderClientInterface;
use Aligent\PrerenderIo\Helper\Config;
use Aligent\PrerenderIo\Model\Indexer\DataProvider\ProductCategories;
use Aligent\PrerenderIo\Model\Url\GetUrlsForCategories;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Indexer\DimensionalIndexerInterface;
use Magento\Framework\Indexer\DimensionProviderInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\StoreDimensionProvider;

class ProductIndexer implements IndexerActionInterface, MviewActionInterface, DimensionalIndexerInterface
{
    private const INDEXER_ID = 'prerender_io_category_product';
    private const DEPLOYMENT_CONFIG_INDEXER_BATCHES = 'indexer/batch_size/';

    /** @var DimensionProviderInterface  */
    private DimensionProviderInterface $dimensionProvider;
    /** @var ProductCategories */
    private ProductCategories $productCategoriesDataProvider;
    /** @var GetUrlsForCategories  */
    private GetUrlsForCategories $getUrlsForCategories;
    /** @var PrerenderClientInterface  */
    private PrerenderClientInterface $prerenderClient;
    /** @var DeploymentConfig  */
    private DeploymentConfig $eploymentConfig;
    /** @var Config  */
    private Config $prerenderConfigHelper;
    /** @var Configurable */
    private Configurable $configurable;
    /** @var int|null  */
    private ?int $batchSize;

    /**
     *
     * @param DimensionProviderInterface $dimensionProvider
     * @param ProductCategories $productCategoriesDataProvider
     * @param GetUrlsForCategories $getUrlsForCategories
     * @param PrerenderClientInterface $prerenderClient
     * @param DeploymentConfig $deploymentConfig
     * @param Config $prerenderConfigHelper
     * @param int|null $batchSize
     */
    public function __construct(
        DimensionProviderInterface $dimensionProvider,
        ProductCategories $productCategoriesDataProvider,
        GetUrlsForCategories $getUrlsForCategories,
        PrerenderClientInterface $prerenderClient,
        DeploymentConfig $deploymentConfig,
        Config $prerenderConfigHelper,
        Configurable $configurable,
        ?int $batchSize = 1000
    ) {
        $this->dimensionProvider = $dimensionProvider;
        $this->productCategoriesDataProvider = $productCategoriesDataProvider;
        $this->getUrlsForCategories = $getUrlsForCategories;
        $this->prerenderClient = $prerenderClient;
        $this->deploymentConfig = $deploymentConfig;
        $this->batchSize = $batchSize;
        $this->prerenderConfigHelper = $prerenderConfigHelper;
        $this->configurable = $configurable;
    }

    /**
     * Execute full indexation
     *
     * @return void
     */
    public function executeFull(): void
    {
        $this->executeList([]);
    }

    /**
     * Execute partial indexation by ID list
     *
     * @param int[] $ids
     * @return void
     */
    public function executeList(array $ids): void
    {
        foreach ($this->dimensionProvider->getIterator() as $dimension) {
            try {
                $this->executeByDimensions($dimension, new \ArrayIterator($ids));
            } catch (FileSystemException|RuntimeException $e) {
                continue;
            }
        }
    }

    /**
     * Execute partial indexation by ID
     *
     * @param int $id
     * @return void
     * @throws LocalizedException
     */
    public function executeRow($id): void
    {
        if (!$id) {
            throw new LocalizedException(
                __('Cannot recache url for an undefined product.')
            );
        }
        $this->executeList([$id]);
    }

    /**
     * Execute materialization on ids entities
     *
     * @param int[] $ids
     * @return void
     */
    public function execute($ids): void
    {
        $this->executeList($ids);
    }

    /**
     * Execute indexing per dimension (store)
     *
     * @param arry $dimensions
     * @param \Traversable $entityIds
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function executeByDimensions(array $dimensions, \Traversable $entityIds): void
    {
        if (count($dimensions) > 1 || !isset($dimensions[StoreDimensionProvider::DIMENSION_NAME])) {
            throw new \InvalidArgumentException('Indexer "' . self::INDEXER_ID . '" supports only Store dimension');
        }
        $storeId = (int)$dimensions[StoreDimensionProvider::DIMENSION_NAME]->getValue();

        if (!$this->prerenderConfigHelper->isRecacheEnabled($storeId)) {
            return;
        }

        $entityIds = iterator_to_array($entityIds);

        // Include configurable product id(s) if the edited product is simple
        $parentIds = $this->configurable->getParentIdsByChild($entityIds);
        $entityIds = array_unique(array_merge($entityIds, $parentIds));

        // get list of category ids for the products
        $categoryIds = $this->productCategoriesDataProvider->getCategoryIdsForProducts($entityIds, $storeId);

        // get urls for the products
        $urls = $this->getUrlsForCategories->execute($categoryIds, $storeId);

        $this->batchSize = $this->deploymentConfig->get(
            self::DEPLOYMENT_CONFIG_INDEXER_BATCHES . self::INDEXER_ID . '/partial_reindex'
        ) ?? $this->batchSize;

        $urlBatches = array_chunk($urls, $this->batchSize);
        foreach ($urlBatches as $batchUrls) {
            $this->prerenderClient->recacheUrls($batchUrls, $storeId);
        }
    }
}
