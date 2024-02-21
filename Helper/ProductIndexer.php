<?php

namespace Aligent\PrerenderIo\Helper;

use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * ProductIndexer helper class
 */
class ProductIndexer
{
    /**
     * @var Configurable
     */
    private Configurable $configurable;

    /**
     * @var ProductFactory
     */
    private ProductFactory $productFactory;

    /**
     * @param Configurable $configurable
     * @param ProductFactory $productFactory
     */
    public function __construct(
        Configurable $configurable,
        ProductFactory $productFactory
    ) {
        $this->configurable = $configurable;
        $this->productFactory = $productFactory;
    }

    /**
     * Returns parent entity id(s)
     *
     * @param $simpleProductId
     * @return array
     */
    public function getParentEntityId($simpleProductId): array
    {
        $parentIds = [];
        $simpleProduct = $this->productFactory->create()->load($simpleProductId);
        if ($simpleProduct->getTypeId() == 'simple') {
            // Get the parent IDs of the simple product (configurable product IDs)
            $parentIds = $this->configurable->getParentIdsByChild($simpleProductId);
        }
        return $parentIds;
    }
}
