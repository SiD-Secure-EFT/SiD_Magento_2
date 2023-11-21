/*
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
define(['uiComponent', 'Magento_Checkout/js/model/payment/renderer-list'], function (Component, rendererList) {
  'use strict'
  rendererList.push({
    type: 'sid',
    component: 'SID_SecureEFT/js/view/payment/method-renderer/sid-method'
  })
  return Component.extend({})
})
