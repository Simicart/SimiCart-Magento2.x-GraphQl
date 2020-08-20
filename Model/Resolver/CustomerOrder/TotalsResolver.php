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
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;

/**
 * Fetches order detail data according to the GraphQL schema
 */
class TotalsResolver implements ResolverInterface
{
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        OrderFactory $orderFactory
    ) {
        $this->orderFactory = $orderFactory;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null) {

        $order = $value['model'];
        // $store = $context->getExtensionAttributes()->getStore();
        // $currency = $store->getCurrentCurrencyCode();
        $currency = $order->getOrderCurrencyCode();

        return [
            'sub_total' => ['value' => (float) $order->getSubtotal(), 'currency' => $currency],
            'grand_total' => ['value' => (float) $order->getGrandTotal(), 'currency' => $currency],
            'tax' => ['value'=> (float) $order->getTaxAmount(), 'currency' => $currency],
            'discount' => ['value'=> (float) $order->getDiscountAmount(), 'currency' => $currency],
            'model' => $order,
        ];
    }
}