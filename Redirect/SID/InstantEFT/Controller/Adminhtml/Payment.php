<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller\Adminhtml;

abstract class Payment extends \Magento\Backend\App\AbstractAction
{
    protected $_coreRegistry;

    public function __construct( \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Translate\InlineInterface $translateInline,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory ) {
        parent::__construct( $context );
        $this->_coreRegistry       = $coreRegistry;
        $this->_fileFactory        = $fileFactory;
        $this->_translateInline    = $translateInline;
        $this->resultPageFactory   = $resultPageFactory;
        $this->resultJsonFactory   = $resultJsonFactory;
        $this->resultLayoutFactory = $resultLayoutFactory;
        $this->resultRawFactory    = $resultRawFactory;
    }

    protected function _initAction()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu( 'SID_InstantEFT::payments' );
        $resultPage->addBreadcrumb( __( 'SID Instant EFT Payments' ), __( 'SID Instant EFT Payments' ) );
        return $resultPage;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed( 'SID_InstantEFT::sid_payments' );
    }
}
