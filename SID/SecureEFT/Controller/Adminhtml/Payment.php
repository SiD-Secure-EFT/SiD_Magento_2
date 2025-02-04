<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Controller\Adminhtml;

use Magento\Framework\App\ActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;

abstract class Payment implements ActionInterface
{
    protected RawFactory $resultRawFactory;
    protected LayoutFactory $resultLayoutFactory;
    protected JsonFactory $resultJsonFactory;
    protected PageFactory $resultPageFactory;
    protected InlineInterface $_translateInline;
    protected FileFactory $_fileFactory;
    protected \Magento\Framework\AuthorizationInterface $_authorization;

    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        InlineInterface $translateInline,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        LayoutFactory $resultLayoutFactory,
        RawFactory $resultRawFactory
    ) {
        $this->_fileFactory        = $fileFactory;
        $this->_translateInline    = $translateInline;
        $this->resultPageFactory   = $resultPageFactory;
        $this->resultJsonFactory   = $resultJsonFactory;
        $this->resultLayoutFactory = $resultLayoutFactory;
        $this->resultRawFactory    = $resultRawFactory;
        $this->_authorization      = $context->getAuthorization();
    }

    public function execute()
    {
        // execute
    }

    protected function _initAction()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('SID_SecureEFT::payments');
        $resultPage->addBreadcrumb(__('SID Secure EFT Payments'), __('SID Secure EFT Payments'));

        return $resultPage;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('SID_SecureEFT::sid_payments');
    }
}
