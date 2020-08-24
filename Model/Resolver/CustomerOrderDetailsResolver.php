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
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;

/**
 * Fetches order detail data according to the GraphQL schema
 */
class CustomerOrderDetailsResolver implements ResolverInterface
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
        $customerId = $context->getUserId();

        /* Guest checking */
        if (!$customerId && 0 === $customerId) {
            throw new GraphQlAuthorizationException(__('The current user cannot perform operations on customer order.'));
        }
        
        if (!isset($args['order_number'])) {
            throw new GraphQlAuthorizationException(__('The order number is required param.'));
        }

        $orderNumber = $args['order_number'];
        $storeId = (string)$context->getExtensionAttributes()->getStore()->getId();
        $order = $this->orderFactory->create()->loadByIncrementIdAndStoreId($orderNumber, $storeId);

        $billingAddress = null;
        $shippingAddress = null;

        $addresses = $order->getAddresses();
        foreach($addresses as $addressModel){
            $addressData = [
                'id' => $addressModel->getId(),
                "customer_id" => $addressModel->getCustomerId(),
                "region" => [
                    "region_code" => $addressModel->getRegionCode(),
                    "region" => $addressModel->getRegion(),
                    "region_id" => $addressModel->getRegionId(),
                ],
                "region_id" => $addressModel->getRegionId(),
                "country_id" => $addressModel->getCountryId(),
                "country_code" => $addressModel->getCountryId(),
                "street" => $addressModel->getStreet(),
                "company" => $addressModel->getCompany(),
                "telephone" => $addressModel->getTelephone(),
                "fax" => $addressModel->getFax(),
                "postcode" => $addressModel->getPostcode(),
                "city" => $addressModel->getCity(),
                "firstname" => $addressModel->getFirstname(),
                "lastname" => $addressModel->getLastname(),
                "middlename" => $addressModel->getMiddlename(),
                "prefix" => $addressModel->getPrefix(),
                "suffix" => $addressModel->getSuffix(),
                "vat_id" => $addressModel->getVatId(),
                "default_shipping" => false,
                "default_billing" => false,
            ];
            if ($addressModel->getAddressType() == \Magento\Sales\Model\Order\Address::TYPE_BILLING) {
                $billingAddress = $addressData;
                $billingAddress["default_shipping"] = false;
                $billingAddress["default_billing"] = true;
            }
            if ($addressModel->getAddressType() == \Magento\Sales\Model\Order\Address::TYPE_SHIPPING) {
                $shippingAddress = $addressData;
                $shippingAddress["default_shipping"] = true;
                $shippingAddress["default_billing"] = false;
            }
        }

        return [
            'id' => $order->getId(),
            'increment_id' => $order->getIncrementId(),
            'order_number' => $order->getIncrementId(),
            'created_at' => $order->getCreatedAt(),
            'grand_total' => $order->getGrandTotal(),
            'status' => $order->getStatus(),
            'billing_address' => $billingAddress,
            'shipping_address' => $shippingAddress,
            'shipping_method' => $order->getShippingMethod(),
            'payment_method' => $order->getPayment()->getMethodInstance()->getTitle(),
            'is_virtual' => $order->getIsVirtual(),
            'model' => $order
        ];
    }
}