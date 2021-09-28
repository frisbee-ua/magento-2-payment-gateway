<?php

namespace Fondy\Fondy\Controller\Api;

/**
 * Interface ApiInterface
 * @package Fondy\Fondy\Api
 */
interface ApiInterface
{
    /**
     * @param string $cartId
     * @param string $orderId
     * @param string $method
     * @return string
     */
    public function payment($cartId, $orderId, $method);
}
