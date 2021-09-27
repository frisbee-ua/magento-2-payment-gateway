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
    protected $_isGateway = false;
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
     * Sidebar payment info block
     *
     * @var string
     */
    protected $_infoBlockType = Instructions::class;

    protected $_logger;

    protected $_canUseCheckout = true;
}
