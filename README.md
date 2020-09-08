Magento is an ecommerce platform built on open source technology which provides online merchants with a flexible shopping cart system, as well as control over the look, content and functionality of their online stores. Magento offers powerful marketing, search engine optimization, and catalog-management tools. Magento's ability to scale allows shops with only a few products and simple needs to easily expand to tens of thousands of products and complex custom behavior without changing platforms.

In order to facilitate the development of e-commerce through Magento, Doku as the largest payment enabler in Indonesia, provides a payment module that can be used easily and quickly (plug & play) on the Magento platform.

The features available in the Magento plugin are:
1. Online payment in various payment channels in almost all banks in Indonesia.
2. Filing refunds online (voids and refunds).
3. Fraud detection.
4. Email confirmation.

# Payment Channel Options Avalaiblitiy on Magento Plugin: #
   
1. Virtual Account:
   - Mandiri
   
## Minimum Requirements ##
This plugin is tested with Magento version 2.3.4
PHP version 7.2.0 or greater

## Manual Instalation ##
1. Copy Jokul_Magento2 folder into your MAGENTO_DIR/app/code directory on your store's webserver.

2. php bin/magento module:status. You should see Jokul_Magento2 on list of disabled modules.

3. php bin/magento module:enable Jokul_Magento2

4. php bin/magento setup:upgrade

5. Run php bin/magento module:status again to ensure Jokul_Magento2 is enabled already.

6. You should flush Magento cache by using php bin/magento cache:flush

7. Compile Magento with newly added module by using php bin/magento setup:di:compile