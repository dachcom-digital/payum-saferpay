# Payum Saferpay

## Language Parameter
The `payer.languageCode` Parameter cannot be set in the ConvertPaymentAction since there is no general language getter available in Payum.
To add this field you need to add a custom Extension (Check [this file](https://github.com/coreshop/PayumSaferpayBundle/blob/master/src/CoreShop/Payum/Saferpay/Extension/ConvertPaymentExtension.php#L77) to get the Idea).

## Copyright and License
Copyright: [DACHCOM.DIGITAL](http://dachcom-digital.ch)
For licensing details please visit [LICENSE.md](LICENSE.md)
