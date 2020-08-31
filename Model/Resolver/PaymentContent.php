<?php

declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver;

use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class PaymentContent implements ResolverInterface
{
    /**
     * @var PaymentInformationManagementInterface
     */
    private $informationManagement;
    public $scopeConfig;

    /**
     * @param PaymentInformationManagementInterface $informationManagement
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        PaymentInformationManagementInterface $informationManagement
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->informationManagement = $informationManagement;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if ($value['code'] === 'banktransfer') {
            return $this->scopeConfig->getValue('payment/banktransfer/instructions');
        }
        return '';
    }

}
