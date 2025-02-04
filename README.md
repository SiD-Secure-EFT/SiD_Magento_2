# SiD_Magento_2

## SiD Secure EFT plugin v2.5.0 for Magento v2.4.7

This is the SiD Secure EFT plugin for Magento 2. Please feel free to contact the Payfast support team at
support@payfast.help should you require any assistance.

## Installation

1. **Download the Plugin**

    - Visit the [releases page](https://github.com/SiD-Secure-EFT/SiD_Magento_2/releases) and
      download [SID.zip](https://github.com/SiD-Secure-EFT/SiD_Magento_2/releases/download/v2.5.0/SID.zip).
    - Extract the contents of `SID.zip`, then upload the newly created **SID** directory into your Magento
      app/code directory (e.g. magentorootfolder/app/code/).

2. **Install the Plugin**

    - Run the following Magento CLI commands:
      ```console
      php bin/magento module:enable SID_SecureEFT
      php bin/magento setup:upgrade
      php bin/magento setup:di:compile
      php bin/magento setup:static-content:deploy
      php bin/magento indexer:reindex
      php bin/magento cache:clean
      ```
3. **Configure the Plugin**

    - Login to the Magento admin panel.
    - Navigate to **Stores > Configuration > Sales > Payment Methods** and click on
      **SID Secure EFT**.
    - Configure the module according to your needs, then click the **Save Config** button.

4. **Update Buyer and Merchant URLs**

    - Login to the [SID Merchant Portal](https://merchant.sidpayment.com/).
    - Click on the **Account Settings** link in the top menu.
    - Submit a request to update the following URLs to include your site URL:
        - **Buyer Return URL:** `https://<Site URL>/sid/redirect/index`
        - **Merchant Notification URL:** `https://<Site URL>/sid/notify`
    - Alternatively, email support@payfast.help to request the updates.

## Collaboration

Please submit pull requests with any tweaks, features or fixes you would like to share.
