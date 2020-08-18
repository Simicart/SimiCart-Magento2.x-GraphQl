<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Wishlist\Model\ResourceModel\Wishlist as WishlistResourceModel;
use Magento\Wishlist\Model\Wishlist;
use Magento\Wishlist\Model\WishlistFactory;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Fetches the Wishlist data according to the GraphQL schema
 */
class WishlistResolver implements ResolverInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var WishlistResourceModel
     */
    private $wishlistResource;

    /**
     * @var WishlistFactory
     */
    private $wishlistFactory;

    /**
     * @param WishlistResourceModel $wishlistResource
     * @param WishlistFactory $wishlistFactory
     */
    public function __construct(
        WishlistResourceModel $wishlistResource,
        WishlistFactory $wishlistFactory,
        ProductRepositoryInterface $productRepository
    )
    {
        $this->wishlistResource = $wishlistResource;
        $this->wishlistFactory = $wishlistFactory;
        $this->productRepository = $productRepository;
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
        $customerId = $context->getUserId();

        /* Guest checking */
        if (!$customerId && 0 === $customerId) {
            throw new GraphQlAuthorizationException(__('The current user cannot perform operations on wishlist'));
        }
        /** @var Wishlist $wishlist */
        $wishlist = $this->wishlistFactory->create();
        $this->wishlistResource->load($wishlist, $customerId, 'customer_id');

        if (null === $wishlist->getId()) {
            return [];
        }

        // Add item to wishlist
        if (!isset($args['product'])) {
            throw new GraphQlAuthorizationException(__('Product is required param.'));
        }

        $store = $context->getExtensionAttributes()->getStore();
        $storeId = (int) $store->getId();
        $product = $this->productRepository->getById($args['product'], false, $storeId);
        $buyRequest = null;
        if (isset($args['data'])) {
            $buyRequest = $args['data']; // is valid buy request data json
        }
        $wishlist->addNewItem($product, $buyRequest);

        return [
            'id' => $wishlist->getId(),
            'sharing_code' => $wishlist->getSharingCode(),
            'updated_at' => $wishlist->getUpdatedAt(),
            'items_count' => $wishlist->getItemsCount(),
            'name' => $wishlist->getName(),
            'model' => $wishlist,
        ];
    }
}
