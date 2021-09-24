<?php

namespace Fondy\Fondy\Block\Form;

/**
 * Abstract class for FondyService payment method form
 */
abstract class Fondy extends \Magento\Payment\Block\Form
{
    protected $_instructions;
    protected $_template = 'form/fondy_form.phtml';
}
