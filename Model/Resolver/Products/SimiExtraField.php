<?php

declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver\Products;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Catalog\Model\Category\FileInfo;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\Registry;
use Magento\Framework\Event\ManagerInterface;
use \Magento\Catalog\Model\ProductFactory;

/**
 * @inheritdoc
 */
class SimiExtraField implements ResolverInterface
{
    /** @var LayoutInterface  */
    private $simiLayout;

    /** @var ManagerInterface  */
    private $eventManager;

    /** @var Registry  */
    private $registry;

    /** @var ProductFactory  */
    private $productFactory;

    /**
     * @param DirectoryList $directoryList
     * @param FileInfo $fileInfo
     */
    public function __construct(
        Registry $registry,
        ManagerInterface $eventManager,
        ProductFactory $productFactory,
        LayoutInterface $simiLayout
    ) {
        $this->registry = $registry;
        $this->simiLayout = $simiLayout;
        $this->productFactory = $productFactory;
        $this->eventManager = $eventManager;
    }
    
    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var \Magento\Catalog\Model\Product $category */
        $product = $value['model'];
        $product = $this->productFactory->create()->load($product->getId());
        $registry = $this->registry;
        $registry->register('product', $product);
        $registry->register('current_product', $product);
        $layout = $this->simiLayout;
        $block_att = $layout->createBlock('Magento\Catalog\Block\Product\View\Attributes');
        $_additional = $block_att->getAdditionalData();
        $this->extraFields = [
            'additional' => $_additional,
        ];
        $this->currentProductModel = $product;
        $this->eventManager->dispatch(
            'simi_simiconnector_graphql_product_detail_extra_field_after',
            ['object' => $this, 'data' => $this->extraFields]
        );
        return json_encode($this->extraFields);
    }
}
