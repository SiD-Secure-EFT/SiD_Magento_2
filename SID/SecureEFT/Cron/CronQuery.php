<?php
/**
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Cron;

use DateInterval;
use DateTime;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use SID\SecureEFT\Model\SID;
use Psr\Log\LoggerInterface;

class CronQuery
{
    protected LoggerInterface $logger;
    protected SID $sidSession;
    protected CollectionFactory $_orderCollectionFactory;
    protected State $_state;
    protected OrderRepositoryInterface $orderRepository;

    public function __construct(
        LoggerInterface $logger,
        CollectionFactory $orderCollectionFactory,
        State $state,
        OrderRepositoryInterface $orderRepository,
        SID $sid,
    ) {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_state                  = $state;
        $this->orderRepository         = $orderRepository;
        $this->sidSession              = $sid;
        $this->logger                 = $logger;
    }

    public function execute()
    {
        $this->_state->emulateAreaCode(
            Area::AREA_FRONTEND,
            function () {
                $cutoffTime = (new DateTime())->sub(new DateInterval('PT10M'))->format('Y-m-d H:i:s');
                $this->logger->info('Cutoff: ' . $cutoffTime);
                $ocf = $this->_orderCollectionFactory->create();
                $ocf->addAttributeToSelect('entity_id');
                $ocf->addAttributeToFilter('status', ['eq' => 'pending_payment']);
                $ocf->addAttributeToFilter('created_at', ['lt' => $cutoffTime]);
                $ocf->addAttributeToFilter('updated_at', ['lt' => $cutoffTime]);

                $orderIds = array();
                foreach ($ocf as $order) {
                    if ($order->getPayment()->getMethod() == "sid") {
                        $orderIds[] = $order->getId();
                    }
                }

                $this->logger->info('Orders for cron: ' . json_encode($orderIds));

                foreach ($orderIds as $orderId) {
                    $order = $this->orderRepository->get($orderId);

                    $this->sidSession->validateOrder($order);
                }
            }
        );
    }
}
