<?php
/*
 * Copyright (c) Aligent Consulting. All rights reserved.
 */

declare(strict_types=1);
namespace Aligent\PrerenderIo\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class GetUrlsForProducts
{
    /** @var CollectionFactory  */
    private CollectionFactory $productCollectionFactory;
    /** @var StoreManagerInterface */
    private StoreManagerInterface $storeManager;
    /** @var Emulation */
    private Emulation $emulation;

    /**
     *
     * @param CollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        Emulation $emulation
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->emulation = $emulation;
    }

    /**
     * Generate product URLs based on URL_REWRITE entries
     *
     * @param array $productIds
     * @param int $storeId
     * @return array
     */
    public function execute(array $productIds, int $storeId): array
    {
        $productCollection = $this->productCollectionFactory->create();
        // do not ignore out of stock products
        $productCollection->setFlag('has_stock_status_filter', true);
        // if array of product ids is empty, just load all products
        if (!empty($productIds)) {
            $productCollection->addIdFilter($productIds);
        }
        $productCollection->setStoreId($storeId);
        $productCollection->addUrlRewrite();

        try {
            /** @var Store $store */
            $store = $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException $e) {
            return [];
        }

        $this->emulation->startEnvironmentEmulation($storeId);
        $urls = [];
        /** @var Product $product */
        foreach ($productCollection as $product) {
            $urlPath = $product->getData('request_path');
            if (empty($urlPath)) {
                continue;
            }
            try {
                $urls[] = $store->getUrl($urlPath);
            } catch (NoSuchEntityException $e) {
                continue;
            }
        }
        $this->emulation->stopEnvironmentEmulation();
        return $urls;
    }
}
