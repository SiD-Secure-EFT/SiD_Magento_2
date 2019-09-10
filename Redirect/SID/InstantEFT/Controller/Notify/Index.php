<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller\Notify;

require_once __DIR__ . '/../AbstractSID.php';

class Index extends \SID\InstantEFT\Controller\AbstractSID
{

    private $storeId;

    /**
     * indexAction
     *
     */
    public function execute()
    {

        $errors   = false;
        $sid_data = array();

        $notify_data = array();
        $post_data   = '';
        // Get notify data
        if ( !$errors ) {
            $sid_data = $this->getPostData();
            if ( $sid_data === false ) {
                $errors = true;
            }
        }

        // Verify security signature

        if ( !$errors ) {
            // Load order

            $orderId       = $sid_data['SID_REFERENCE'];
            $this->_order  = $this->_orderFactory->create()->loadByIncrementId( $orderId );
            $this->storeId = $this->_order->getStoreId();

            $status = $sid_data['SID_STATUS'];

            // Update order additional payment information

            if ( $status == "COMPLETED" ) {
                $this->_order->setStatus( \Magento\Sales\Model\Order::STATE_PROCESSING );
                $this->_order->save();
                $this->_order->addStatusHistoryComment( "Notify Response, Transaction has been approved, TransactionID: " . $sid_data['SID_TNXID'], \Magento\Sales\Model\Order::STATE_PROCESSING )->setIsCustomerNotified( false )->save();
            } elseif ( $status == "CANCELLED" ) {
                $this->_order->setStatus( \Magento\Sales\Model\Order::STATE_CANCELED );
                $this->_order->save();
                $this->_order->addStatusHistoryComment( "Notify Response, The User Failed to make Payment with SId Payment due to transaction being declined, TransactionID: " . $sid_data['SID_TNXID'], \Magento\Sales\Model\Order::STATE_PROCESSING )->setIsCustomerNotified( false )->save();
            } else {
                $this->_order->setStatus( \Magento\Sales\Model\Order::STATE_CANCELED );
                $this->_order->save();
                $this->_order->addStatusHistoryComment( "Notify Response, The User Failed to make Payment with SId Payment due to transaction being declined, TransactionID: " . $sid_data['SID_TNXID'], \Magento\Sales\Model\Order::STATE_PROCESSING )->setIsCustomerNotified( false )->save();
            }
        }
    }

    // Retrieve post data
    public function getPostData()
    {
        // Posted variables from ITN
        $nData = $_POST;

        // Strip any slashes in data
        foreach ( $nData as $key => $val ) {
            $nData[$key] = stripslashes( $val );
        }

        // Return "false" if no data was received
        if ( sizeof( $nData ) == 0 ) {
            return ( false );
        } else {
            return ( $nData );
        }

    }

    /**
     * saveInvoice
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function saveInvoice()
    {
        // Check for mail msg
        $invoice = $this->_order->prepareInvoice();

        $invoice->register()->capture();

        /**
         * @var \Magento\Framework\DB\Transaction $transaction
         */
        $transaction = $this->_transactionFactory->create();
        $transaction->addObject( $invoice )
            ->addObject( $invoice->getOrder() )
            ->save();

        $this->_order->addStatusHistoryComment( __( 'Notified customer about invoice #%1.', $invoice->getIncrementId() ) );
        $this->_order->setIsCustomerNotified( true );
        $this->_order->save();
    }
}
