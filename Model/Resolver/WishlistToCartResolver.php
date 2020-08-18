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
use Magento\Wishlist\Model\Item;
use Magento\Wishlist\Model\Wishlist;
use Magento\Wishlist\Model\WishlistFactory;
use Magento\Wishlist\Model\ResourceModel\Wishlist as WishlistResourceModel;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Checkout\Model\Cart;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;

/**
 * Fetches the Wishlist data according to the GraphQL schema
 */
class WishlistToCartResolver implements ResolverInterface
{
    /**
     * @var WishlistResourceModel
     */
    private $wishlistResource;

    /**
     * @var WishlistFactory
     */
    private $wishlistFactory;

    /**
     * @var Item
     */
    private $wishlistItem;

    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var Cart
     */
    private $cart;

    /**
     * @param Item $wishlistItem
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        WishlistResourceModel $wishlistResource,
        WishlistFactory $wishlistFactory,
        Item $wishlistItem,
        GetCartForUser $getCartForUser,
        Cart $cart
    )
    {
        $this->wishlistResource = $wishlistResource;
        $this->wishlistFactory = $wishlistFactory;
        $this->wishlistItem = $wishlistItem;
        $this->getCartForUser = $getCartForUser;
        $this->cart = $cart;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null) {
        if (!isset($args['item_id']) || empty($args['item_id'])) {
            throw new GraphQlInputException(__('Required parameter "item_id" is missing'));
        }
        if (!isset($args['cart_id']) || empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        $customerId = $context->getUserId();

        /* Guest checking */
        if (!$customerId && 0 === $customerId) {
            throw new GraphQlAuthorizationException(__('The current user cannot perform operations on wishlist'));
        }

        $maskedCartId = $args['cart_id'];
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $quote = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
        $this->cart->setQuote($quote);
        $this->wishlistItem->load($args['item_id']);
        if ($this->wishlistItem->getId()) {
            $result = $this->wishlistItem->addToCart($this->cart, true); // must add to wishlist with full request options before
            // if ($result) {
            //     $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
            //     return [
            //         'model' => $cart,
            //     ];
            // }
        } else {
            throw new GraphQlAuthorizationException(__('This wishlist item does not exists.'));
        }

        /** @var Wishlist $wishlist */
        $wishlist = $this->wishlistFactory->create();
        // $this->wishlistResource->load($wishlist, $customerId, 'customer_id');
        $wishlist->loadByCustomerId($customerId, true);

        if (null === $wishlist->getId()) {
            return [];
        }

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
