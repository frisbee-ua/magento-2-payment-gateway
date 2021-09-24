<?php

namespace Fondy\Fondy\Observer;

use Fondy\Fondy\Builder\RequestBuilder;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class OrderRefundedObserver implements ObserverInterface
{
    /**
     * @var RequestBuilder
     */
    private $requestBuilder;

    public function __construct(
        RequestBuilder $requestBuilder
    ) {
        $this->requestBuilder = $requestBuilder;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /**
         * @var \Magento\Sales\Model\Order $order
         */
        $order = $observer->getData('order');
        $extOrderId = $order->getExtOrderId();

        if ($extOrderId) {
            $amount = $order->getBaseTotalOfflineRefunded();
            $this->requestBuilder->refund($extOrderId, $amount);
        }
    }
}
