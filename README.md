# DOKU Magento Plugin
​
DOKU makes it easy for you accept payments from various channels. DOKU also highly concerned the payment experience for your customers when they are on your store. With this plugin, you can set it up on your Magento website easily and make great payment experience for your customers.
​
## Requirements
​
- Magento v2.3 or higher. This plugin is tested with Magento v2.3.4, v.2.3.6, v.2.4.0, v.2.4.1
- PHP v7.4.0 or higher. 
- MySQL v8.0 or higher
- DOKU account:
    - For testing purpose, please register to the Sandbox environment and retrieve the Client ID & Secret Key. Learn more about the sandbox environment [here](https://jokul.doku.com/docs/docs/getting-started/explore-sandbox)
    - For real transaction, please register to the Production environment and retrieve the Client ID & Secret Key. Learn more about the production registration process [here](https://jokul.doku.com/docs/docs/getting-started/register-user)

## DOKU Magento Already Supported `doku_log`
​
This `doku_log` is useful to help simplify the process of checking if an issue occurs related to the payment process using the DOKU Plugin. If there are problems or problems using the plugin, you can contact our team by sending this doku_log file. `Doku_log` will record all transaction processes from any channel by date.

​
## How to use and take doku_log file?
​
1. Open your `MAGENTO_DIR` directory on your store's webserver.
2. Create folder `doku_log` in your directory store's, so plugin will automatically track log in your store's webserver.
3. Then check `doku_log` and open file in your store's webserver.
4. You will see `doku log` file by date.
5. And you can download the file. 
6. If an issue occurs, you can send this `doku_log` file to the team to make it easier to find the cause of the issue.


## Payment Channels Supported
​
- Checkout Page
Easily embed our well-crafted yet customizable DOKU payment page for your website. With a single integration, you can start accepting payments on your web. With a single integration, Checkout Page allows you to accept payments from various DOKU payment channels. 


## How to Install
​
1. Download this repo
2. Copy `Jokul` folder into your `MAGENTO_DIR/app/code` directory on your store's webserver.
3. Run `php bin/magento module:status`. You should see `Jokul_Magento2` on list of disabled modules.
4. Run `php bin/magento module:enable Jokul_Magento2`
5. Run `php bin/magento setup:upgrade`
6. Run `php bin/magento module:status` again to ensure `Jokul_Magento2` is enabled already.
7. You should flush Magento cache by running `php bin/magento cache:flush`
8. Compile Magento with newly added module by running `php bin/magento setup:di:compile`
9. You may run flush Magento cache again `php bin/magento cache:flush`
​
## Plugin Usage
​
### General Configuration
​
1. Login to your Magento Admin Panel
2. Click Store > Configuration
3. Click Sales > Payment Methods
4. You will find "DOKU"
5. Dropdown the arrow icon to see the details
6. Here is the fileds that you required to set:
​
    ![General Configuration](https://i.ibb.co/vV61CVZ/screencapture-sandboxenv-devmagento-admin123-admin-system-config-edit-section-payment-key-522ef14376.png)
    
    - **Production Client ID**: Client ID you retrieved from the Production environment DOKU Back Office
    - **Sandbox Client ID**: Client ID you retrieved from the Sandbox environment DOKU Back Office
    - **Production Secret Key**: Secret Key you retrieved from the Production environment DOKU Back Office
    - **Sandbox Secret Key**: Secret Key you retrieved from the Sandbox environment DOKU Back Office
    - **Environment**: For testing purpose, select Sandbox. For accepting real transactions, select Production
    - **Expiry Time**: Input the time that for VA expiration in minutes
    - **Email Sender Adress**: You can fill this coloumn with your email address. This will later be used as info to send notifications to your customers
    - **Email Sender Name**: You can fill this coloumn with your name. This will later be used as info to send notifications to your customers
    - **BCC Email Adress**: You can fill this coloumn other email adress. This will later be used to send notifications to your customers
    - **Notification URL**: Copy this URL and paste the URL into the DOKU Back Office. Learn more about how to setup Notification URL [here](https://jokul.doku.com/docs/docs/after-payment/setup-notification-url)
    **QRIS Notification URL** : Copy this URL and and contact our support team to help paste in QRIS Backoffice. This channel only support if youre enabling Checkout Page as a payment method.
    - **Email Notifications** : You can activated the feature send emails for VA and O2O channels. This email contains how to pay for the VA or Paycode.
    - **Sub Account Feature** : This feature helps you to routing your payment into your Sub Account ID. You can see the details for payment flow if youre using this feature [here](https://jokul.doku.com/docs/docs/jokul-sub-account/jokul-sub-account-overview)
7. Click Save Config button
8. Go Back to Payments Tab
9. Now your customer should be able to see the payment channels and you start receiving payments
​

### DOKU Checkout Configuration

To show the DOKU Checkout options to your customers, simply toggle the channel that you wish to show. DOKU Checkout allows you to accept payments from various DOKU payment channels. You can enable or disable the payment channel that you want to show in your store view in DOKU Backoffice Configuration.

You can also click Manage to edit how the DOKU Checkout channels will be shown to your customers by clicking the Manage button. 
Below you can update the QRIS Credential that youre already get from our Support Team.
