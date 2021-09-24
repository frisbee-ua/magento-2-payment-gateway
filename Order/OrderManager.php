<?php

namespace Fondy\Fondy\Order;

use Fondy\Fondy\Configuration\ConfigurationService;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Order;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\ResourceModel\Order as ResourceModelOrder;
use Exception;

final class OrderManager
{
    const ORDER_STATUS_TOTALLY_REFUNDED = 'refunded';
    const ORDER_STATUS_PARTIALLY_REFUNDED = 'refunded_partial';

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var ResourceModelOrder
     */
    private $resourceModelOrder;

    public function __construct(
        ConfigurationService $configurationService,
        OrderRepository $orderRepository,
        ResourceConnection $resourceConnection,
        ResourceModelOrder $resourceModelOrder
    ) {
        $this->configurationService = $configurationService;
        $this->orderRepository = $orderRepository;
        $this->resourceConnection = $resourceConnection;
        $this->resourceModelOrder = $resourceModelOrder;
    }

    /**
     * @param OrderInterface $order
     * @return void
     * @throws \Exception
     */
    public function setOrderStatusProcessing(OrderInterface $order)
    {
        $orderStatusProcessing = $this->configurationService->getOptionOrderStatusProcessing();

        $this->setStatus($order, $orderStatusProcessing);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    public function setOrderStatusCancelled(Order $order)
    {
        $orderStatusCanceled = $this->configurationService->getOptionOrderStatusCanceled();

        $this->setStatus($order, $orderStatusCanceled);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    public function setOrderStatusPaid(Order $order)
    {
        $orderStatusPaid = $this->configurationService->getOptionOrderStatusPaid();

        $this->setStatus($order, $orderStatusPaid);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    public function setOrderStatusTotallyRefunded(Order $order)
    {
        $orderStatusTotallyRefunded = $this->getOrderStatusTotallyRefunded();

        $this->setStatus($order, $orderStatusTotallyRefunded);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    public function setOrderStatusPartiallyRefunded(Order $order)
    {
        $orderStatusPartiallyRefunded = $this->getOrderStatusPartiallyRefunded();

        $this->setStatus($order, $orderStatusPartiallyRefunded);
    }

    /**
     * @return string
     */
    public function getOrderStatusTotallyRefunded()
    {
        return self::ORDER_STATUS_TOTALLY_REFUNDED;
    }

    /**
     * @return string
     */
    public function getOrderStatusPartiallyRefunded()
    {
        return self::ORDER_STATUS_PARTIALLY_REFUNDED;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param string $orderStatus
     * @return void
     */
    public function setStatus(Order $order, string $orderStatus)
    {
        $order->setState($orderStatus);
        $order->setStatus($order->getConfig()->getStateDefaultStatus($orderStatus));
        $this->orderRepository->save($order);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param string $comment
     * @param bool $status
     * @param bool $isVisibleOnFront
     * @return \Magento\Sales\Api\Data\OrderStatusHistoryInterface
     */
    public function addCommentToStatusHistory(Order $order, $comment, $status = false, $isVisibleOnFront = false)
    {
        try {
            return $order->addCommentToStatusHistory($comment, $status, $isVisibleOnFront);
        } catch (Exception $exception) {
            return $order->addStatusHistoryComment($comment, $status);
        }
    }

    /**
     * Get order identifier by Reservation
     *
     * @param string $reservationId
     * @return false|int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOrderByReservationId($reservationId)
    {
        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()->from($this->resourceModelOrder->getMainTable())
            ->where('reservation_id = :reservation_id');

        return $connection->fetchOne($select, [':reservation_id' => (string) $reservationId]);
    }

    /**
     * @param string $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getOrderById($orderId)
    {
        return $this->orderRepository->get($orderId);
    }
}
