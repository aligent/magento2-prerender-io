<?php
/*
 * Copyright (c) Aligent Consulting. All rights reserved.
 */

declare(strict_types=1);
namespace Aligent\PrerenderIo\Model\Url;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class GetUrlsForCategories
{
    /** @var CollectionFactory  */
    private CollectionFactory $categoryCollectionFactory;
    /** @var StoreManagerInterface */
    private StoreManagerInterface $storeManager;
    /** @var Emulation */
    private Emulation $emulation;

    /**
     *
     * @param CollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     */
    public function __construct(
        CollectionFactory $categoryCollectionFactory,
        StoreManagerInterface $storeManager,
        Emulation $emulation
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
        $this->emulation = $emulation;
    }

    /**
     * Generate category URLs based on URL_REWRITE entries
     *
     * @param array $categoryIds
     * @param int $storeId
     * @return array
     */
    public function execute(array $categoryIds, int $storeId): array
    {
        $categoryCollection = $this->categoryCollectionFactory->create();
        // if array of category ids is empty, just load all categories
        if (!empty($categoryIds)) {
            $categoryCollection->addIdFilter($categoryIds);
        }
        $categoryCollection->setStoreId($storeId);
        $categoryCollection->addUrlRewriteToResult();

        try {
            /** @var Store $store */
            $store = $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException $e) {
            return [];
        }

        $this->emulation->startEnvironmentEmulation($storeId);
        $urls = [];
        /** @var Category $category */
        foreach ($categoryCollection as $category) {
            $urlPath = $category->getData('request_path');
            if (empty($urlPath)) {
                continue;
            }
            try {
                // remove trailing slashes from urls
                $urls[] = rtrim($store->getUrl($urlPath), '/');
            } catch (NoSuchEntityException $e) {
                continue;
            }
        }
        $this->emulation->stopEnvironmentEmulation();
        return $urls;
    }
}
