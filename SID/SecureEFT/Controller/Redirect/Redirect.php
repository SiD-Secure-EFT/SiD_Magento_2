<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Controller\Redirect;

use Exception;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use SID\SecureEFT\Controller\AbstractSID;
use SID\SecureEFT\Model\Config;

require_once __DIR__ . '/../AbstractSID.php';

class Redirect extends AbstractSID
{
    protected $resultPageFactory;
    protected $_configMethod = Config::METHOD_CODE;
    public const CARTPATH = "checkout/cart";

    public function execute()
    {
        $page_object           = $this->pageFactory->create();
        $resultRedirectFactory = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $this->_initCheckout();
        } catch (LocalizedException $e) {
            $this->_logger->debug(__METHOD__ . ' : ' . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, $e->getMessage());

            return $resultRedirectFactory->setPath(self::CARTPATH);
        } catch (Exception $e) {
            $this->_logger->debug(__METHOD__ . ' : ' . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, __('We can\'t start SID Checkout.'));

            return $resultRedirectFactory->setPath(self::CARTPATH);
        }

        return $page_object;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return $this->logger->debug("Invalid request exception when attempting to validate CSRF");
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function getResponse()
    {
        // getResponse
    }
}
