<?php

namespace Fondy\Fondy\Model;

use Magento\Payment\Block\Info\Instructions;

/**
 * Class Fondy
 * @package Fondy\Fondy\Model
 */
class Fondy extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'fondy';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = false;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Sidebar payment info block
     *
     * @var string
     */
    protected $_infoBlockType = Instructions::class;
}
