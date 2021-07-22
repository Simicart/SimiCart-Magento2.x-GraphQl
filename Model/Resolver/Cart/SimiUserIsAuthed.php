<?php
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver\Cart;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

class SimiUserIsAuthed implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @param GetCartForUser $stockState
     */
    public function __construct(
        GetCartForUser $getCartForUser
    ) {
        $this->getCartForUser = $getCartForUser;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $tokenValid = false;
        $cartEditable = false;

        if ($context->getExtensionAttributes()->getIsCustomer()) {
            $tokenValid = true;
            if ($args && $args['cart_id']) {
                try {
                    $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
                    $maskedCartId = $args['cart_id'];
                    $quote = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
                    if ($quote && $quote->getId()) {
                        $cartEditable = true;
                    }
                } catch (\Exception $e) {

                }
            }
        }

        return [
            'cart_editable' => $cartEditable,
            'token_valid' => $tokenValid,
        ];
    }
}
