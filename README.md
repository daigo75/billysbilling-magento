#Billy's Billing Magento Extension

Invoice extension for Magento from the Danish accounting program [Billy's Billing](http://www.billysbilling.dk/) via [Billy's Billing API](http://dev.billysbilling.dk/).

Please read [API Terms](https://dev.billysbilling.dk/api-terms) before use.

##Installation
Download code and move the files/folders to positions inside Magento corresponding to the current folder structure.
An overview can be seen below:
```
app/code/local/BillysBilling -> app/code/local/
app/etc/modules/BillysBilling_Invoicer.xml -> app/etc/modules/
```
Remember to flush the Magento cache, which can be done by logging into the Admin Panel, clicking System, then Cache Management and finally clicking Flush Magento Cache.

##First time usage
1. Log into your Magento Admin Panel.
2. Navigate to the Billy's Billing config page through System -> Configuration and then click on the Billy's Billing config page in the left pane.
3. Retrieve your API key from Billy's Billing (found in the API page under organization settings).
4. Enter your API key in the topmost textfield and Save Config.
5. Choose shipping product, sales account and VAT model in the select boxes (products, account and VAT models retrieved from your Billy's Billing account) and Save Config.
6. You will now get each order you invoice in Magento invoiced in Billy's Billing.