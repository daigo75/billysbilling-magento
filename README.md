#Billy's Billing Magento Extension

_This extension is a beta version_. If you experience any bugs, please report them on the [Issues page](https://github.com/billysbilling/billysbilling-magento/issues).

Invoice extension for Magento from the Danish accounting program [Billy's Billing](http://www.billysbilling.dk/) via [Billy's Billing API](http://dev.billysbilling.dk/).

Please read [API Terms](https://dev.billysbilling.dk/api-terms) before use.

##Installation
Download code and move the files/folders to positions inside Magento corresponding to the current folder structure.
An overview can be seen below:
```
app/code/community/BillysBilling -> app/code/community/
app/etc/modules/BillysBilling_Invoicer.xml -> app/etc/modules/
```
Remember to flush the Magento cache, which can be done by logging into the Admin Panel, clicking System, then Cache Management and finally clicking Flush Magento Cache. It might also be necessary to use the Flush Cache Storage.

##First time usage
1. Log into your Magento Admin Panel.
2. Navigate to the Billy's Billing config page through System -> Configuration and then click on the Billy's Billing config page in the left pane.
 * If you receive a 404 or Access Denied error, go to System -> Permissions -> Roles, select Administrators and click the Reset button in the top. Then log out and in again, and you should now be able to access the configuration page.
3. Retrieve your API key from Billy's Billing (found in the API page under organization settings).
4. Enter your API key in the topmost textfield and Save Config.
5. Choose shipping product, sales account and VAT model in the select boxes (products, account and VAT models retrieved from your Billy's Billing account) and Save Config.
6. You will now get each order you invoice in Magento invoiced in Billy's Billing.

##Magento support
Currently supports the following Magento versions:
* v1.7 (v1.7.0.2)
* v1.6 (v1.6.2.0)
* v1.5 (v1.5.1.0)
* v1.4 (v1.4.2.0)

##Version history
###0.9.4
* Added unit testing

###0.9.3
* Added support for earlier Magento versions

###0.9.2
* Added support for discount codes
  * Billy's Billing applies discounts on prices including tax and the taxes are applied after the discount. This should therefore be configured the same way in Magento, which is done through System -> Configuration -> Tax under Calculation Settings. Apply Customer Tax should be set to After Discount and Apply Discount On Prices should be set to Including Tax.
* Fixed minor bug regarding error notifications before API key is entered

###0.9.1
* Added error handling with notification support in Magento admin

###0.9.0
* Initial release of the extension
