<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver\CustomerOrder;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\OrderFactory;
use Magento\Catalog\Helper\Image;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;

/**
 * Fetches order detail data according to the GraphQL schema
 */
class ItemsResolver implements ResolverInterface
{
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var Image
     */
    private $imageHelper;

    /**
     * @var AssetRepository
     */
    protected $assetRepo;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        OrderFactory $orderFactory,
        Image $imageHelper
    ) {
        $this->orderFactory = $orderFactory;
        $this->imageHelper = $imageHelper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null) {

        $order = $value['model'];
        $currency = $order->getOrderCurrencyCode();
        $items = [];
        foreach($order->getAllVisibleItems() as $item){
            $product = $item->getProduct();
            $imageUrl = $this->imageHelper->init($product, 'product_page_image_small')
                ->setImageFile($product->getImage())->resize(100, 100)->getUrl();
            $items[] = [
                'id'            => $item->getId(),
                'name'          => $item->getName(),
                'url_key'       => $product->getUrlKey(),
                'image'         => $imageUrl,
                'sku'           => $item->getSku(),
                'price'         => (float) $item->getPrice(),
                'qty'           => $item->getQtyOrdered(),
                'discount'      => (float) $item->getDiscountAmount(),
                'row_total'     => (float) $item->getRowTotal()
            ];
        }

        return $items;
    }
}