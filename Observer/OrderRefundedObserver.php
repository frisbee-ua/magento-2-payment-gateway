<?php

namespace Fondy\Fondy\Observer;

use Fondy\Fondy\Builder\RequestBuilder;
use Fondy\Fondy\Order\OrderManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\TransactionRepositoryInterface;

class OrderRefundedObserver implements ObserverInterface
{
    /**
     * @var RequestBuilder
     */
    private $requestBuilder;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var OrderManager
     */
    private $orderManager;

    public function __construct(
        RequestBuilder $requestBuilder,
        TransactionRepositoryInterface $transactionRepository,
        OrderManager $orderManager
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->transactionRepository = $transactionRepository;
        $this->orderManager = $orderManager;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /**
         * @var \Magento\Sales\Model\Order\Payment $payment
         */
        $payment = $observer->getData('payment');

        /**
         * @var \Magento\Sales\Model\Order\Creditmemo $creditmemo
         */
        $creditmemo = $observer->getData('creditmemo');

        /**
         * @var \Magento\Sales\Model\Order $order
         */
        $order = $payment->getOrder();
        $extOrderId = $order->getExtOrderId();

        if ($extOrderId) {
            $amount = $creditmemo->getGrandTotal();
            $this->requestBuilder->refund($order, $extOrderId, $amount);

            if ($order->getGrandTotal() <= $payment->getAmountRefunded()) {
                $orderStatus = $this->orderManager->getOrderStatusTotallyRefunded();
            } else {
                $orderStatus = $this->orderManager->getOrderStatusPartiallyRefunded();
            }

            $message = $this->requestBuilder->getStatusMessage();
            $comment = sprintf(
                'Message: %s Frisbee ID: %s',
                $message,
                isset($data['order_id']) ? $extOrderId : ''
            );

            $this->orderManager->addCommentToStatusHistory($order, $comment);
            $this->orderManager->setStatus($order, $orderStatus);
        }
    }
}
