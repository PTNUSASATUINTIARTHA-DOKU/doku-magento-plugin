# Jokul Magento Plugin
​
Jokul makes it easy for you accept payments from various channels. Jokul also highly concerned the payment experience for your customers when they are on your store. With this plugin, you can set it up on your Magento website easily and make great payment experience for your customers.
​
## Requirements
​
- Magento v2.3 or higher. This plugin is tested with Magento v2.3.4, v.2.3.6
- PHP v7.4.0 or higher
- MySQL v8.0 or higher
- Jokul account:
    - For testing purpose, please register to the Sandbox environment and retrieve the Client ID & Secret Key. Learn more about the sandbox environment [here](https://jokul.doku.com/docs/docs/getting-started/explore-sandbox)
    - For real transaction, please register to the Production environment and retrieve the Client ID & Secret Key. Learn more about the production registration process [here](https://jokul.doku.com/docs/docs/getting-started/register-user)
​
## Payment Channels Supported
​
1. Virtual Account:
    - BCA VA
    - Bank Mandiri VA
    - Bank Syariah Indonesia VA
    - Permata VA
    - DOKU VA

2. Credit Card
3. Alfamart O2O
​
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
4. You will find "Jokul"
5. Dropdown the arrow icon to see the details
6. Here is the fileds that you required to set:
​
    ![General Configuration](https://i.ibb.co/qyCfkXR/Screen-Shot-2021-03-25-at-01-40-53.png)
    
    - **Production Client ID**: Client ID you retrieved from the Production environment Jokul Back Office
    - **Sandbox Client ID**: Client ID you retrieved from the Sandbox environment Jokul Back Office
    - **Production Secret Key**: Secret Key you retrieved from the Production environment Jokul Back Office
    - **Sandbox Secret Key**: Secret Key you retrieved from the Sandbox environment Jokul Back Office
    - **Environment**: For testing purpose, select Sandbox. For accepting real transactions, select Production
    - **Expiry Time**: Input the time that for VA expiration in minutes
    - **Email Sender Adress**: You can fill this coloumn with your email address. This will later be used as info to send notifications to your customers
    - **Email Sender Name**: You can fill this coloumn with your name. This will later be used as info to send notifications to your customers
    - **BCC Email Adress**: You can fill this coloumn other email adress. This will later be used to send notifications to your customers
    - **Notification URL**: Copy this URL and paste the URL into the Jokul Back Office. Learn more about how to setup Notification URL [here](https://jokul.doku.com/docs/docs/after-payment/setup-notification-url)
7. Click Save Config button
8. Go Back to Payments Tab
9. Now your customer should be able to see the payment channels and you start receiving payments
​
### VA Configuration
​
This is the preview of the Virtual Account Configuration
​
![VA Configuration](https://i.ibb.co/WxZfMZd/Screen-Shot-2021-03-25-at-01-41-13.png)
​
To show the VA options to your customers, simply dropdown the channel that you wish to show.
​
![VA Configuration Details](https://i.ibb.co/PYJGf8w/Screen-Shot-2021-03-25-at-01-41-04.png)
​
You can also edit how the VA channels will be shown to your customers by inputing below:  
​
- **Title**: Input the title. This title will be visible in your store view
- **Description**: Input the description. This description will be visible in your store view
- **Discount Amount**: Fill in the amount of discount you provide. Input number only. For Percentage Type, the number is `0-100`
- **Discount Type**: Select the type of discount. Options: Fixed amount, Percentage
- **Admin Fee**: Fill in the amount of admin fee you provide. Input number only. For Percentage Type, the number is `0-100`
- **Admin Fee Type**: Select the type of admin fee. Options: Fixed amount, Percentage

### Credit Card Configuration
​
This is the preview of the Credit Card Configuration
​
![Credit Card Configuration](https://i.ibb.co/160B9JH/Screen-Shot-2021-05-04-at-10-57-23.png)
​
To show the CC options to your customers, simply dropdown the channel that you wish to show.
​
![Credit Card Configuration Details](https://i.ibb.co/kX9rbtL/Screen-Shot-2021-05-04-at-10-58-38.png)
​
You can also edit how the CC channels will be shown to your customers by inputing below:  
​
- **Title**: Input the title. This title will be visible in your store view
- **Description**: Input the description. This description will be visible in your store view
- **Discount Amount**: Fill in the amount of discount you provide. Input number only. For Percentage Type, the number is `0-100`
- **Discount Type**: Select the type of discount. Options: Fixed amount, Percentage
- **Admin Fee**: Fill in the amount of admin fee you provide. Input number only. For Percentage Type, the number is `0-100`
- **Admin Fee Type**: Select the type of admin fee. Options: Fixed amount, Percentage
- **CC Form - Background Color**: Setup the color form for the Background Credit Card form
- **CC Form - Label Color**: Setup the color form for the Label Credit Card form
- **CC Form - Button Background Color**: Setup the color form the Button Background color form
- **CC Form - Button Font Color**: Setup the color form the Button Font Color form
- **Languange**: Setup the languange for Credit Card form

### Alfamart O2O Configuration
​
This is the preview of the Alfamart O2O Configuration
​
![Alfamart O2O Configuration](https://i.ibb.co/160B9JH/Screen-Shot-2021-05-04-at-10-57-23.png)
​
To show the Alfamart O2O options to your customers, simply dropdown the channel that you wish to show.
​
![Alfamart O2O Configuration Details](https://i.ibb.co/qnqjqzw/Screen-Shot-2021-05-04-at-11-05-10.png)
​
You can also edit how the Alfamart O2O channels will be shown to your customers by inputing below:  
​
- **Title**: Input the title. This title will be visible in your store view
- **Description**: Input the description. This description will be visible in your store view
- **Discount Amount**: Fill in the amount of discount you provide. Input number only. For Percentage Type, the number is `0-100`
- **Discount Type**: Select the type of discount. Options: Fixed amount, Percentage
- **Admin Fee**: Fill in the amount of admin fee you provide. Input number only. For Percentage Type, the number is `0-100`
- **Admin Fee Type**: Select the type of admin fee. Options: Fixed amount, Percentage
- **Footer Message**: Input the footer message. This title will be visible in payment receipt paper
