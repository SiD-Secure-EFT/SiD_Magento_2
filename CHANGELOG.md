# Changelog

## [2.5.0](https://github.com/SiD-Secure-EFT/SiD_Magento_2/releases/tag/v2.5.0)

### Added

- Compatibility update for Magento 2.4.7 and PHP 8.2.

## [2.4.5](https://github.com/SiD-Secure-EFT/SiD_Magento_2/releases/tag/v2.4.5)

### Fixed

- Fetch transaction failing on new orders.

## [2.4.4](https://github.com/SiD-Secure-EFT/SiD_Magento_2/releases/tag/v2.4.4)

### Added

- Compatibility update for PHP 8.1 and Magento 2.4.4+.

### Changed

- Updated SiD API call.

## [2.4.3](https://github.com/SiD-Secure-EFT/SiD_Magento_2/releases/tag/v2.4.3)

### Changed

- Only use the 'last' transaction in the Cron query method.
- Process only SiD orders in a pending state for the Cron query method.

## [2.4.2](https://github.com/SiD-Secure-EFT/SiD_Magento_2/releases/tag/v2.4.2)

### Added

- Consolidated Cron class into a single file for Magento 2.4.x.

### Fixed

- Blocked double order processing on multiple requests.
- Improved multisite scope handling for Fetch and Cron.

## [2.4.1](https://github.com/SiD-Secure-EFT/SiD_Magento_2/releases/tag/v2.4.1)

### Fixed

- Cron query method not firing as expected on some configurations.
- 'Fetch' query method not updating order status from the backend.
- Missing order information after successful payment.

### Removed

- Redirect button.

### Changed

- Code refactor and format.
- Improved IPN reliability.

## [2.4.0](https://github.com/SiD-Secure-EFT/SiD_Magento_2/releases/tag/v2.4.0)

### Added

- Compatibility with Magento 2.4.
- Saved payment transaction data to order.

### Removed

- iFrame.

### Changed

- Improved code quality.
- Changed namespace from InstantEFT to SecureEFT.

## [2.3.4](https://github.com/SiD-Secure-EFT/SiD_Magento_2/releases/tag/v2.3.4)

### Fixed

- Scope config issues for multi-stores.

### Changed

- Improved error handling.
