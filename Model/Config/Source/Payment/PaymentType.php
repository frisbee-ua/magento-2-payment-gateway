<?php

namespace Fondy\Fondy\Model\Config\Source\Payment;

use Magento\Framework\Data\OptionSourceInterface;

final class PaymentType implements OptionSourceInterface
{
    const TYPE_REDIRECT = 'redirect';
    const TYPE_EMBEDDED = 'embedded';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::TYPE_REDIRECT, 'label' => __('Redirect')],
            ['value' => self::TYPE_EMBEDDED, 'label' => __('Embedded')],
        ];
    }

    /**
     * @param string $type
     * @return bool
     */
    public function isTypeRedirect($type)
    {
        return $type === self::TYPE_REDIRECT;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function isTypeEmbedded($type)
    {
        return $type === self::TYPE_EMBEDDED;
    }
}
