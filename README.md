Stop russian war. **🇺🇦 Free Ukraine!**

# shippingmax

A module to add new pick up shipping methods for [OpenMage](https://github.com/OpenMage/magento-lts).

Composer dependencies:
* [adamlc/address-format](https://github.com/adamlc/address-format)
* [giggsey/libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php)
* [azuyalabs/yasumi](https://github.com/azuyalabs/yasumi)

Included dependencies:
* [owebia/shipping](https://github.com/owebia/magento1-module-advanced-shipping) (2.6.10-lite)
* [leaflet](https://leafletjs.com/) (1.8.0)

External services:
* [Nominatim](https://nominatim.org/): addresses geocoding (results are stored in database)
* [DaData](https://dadata.ru/api/clean/address/): addresses geocoding (results are stored in database, only for RU and KZ), account required

## New configuration options

In **System / Configuration / Delivery times**, you can configure _delivery times_ by country of delivery. You are seeing a `*` in section head? This is because all times are not yet configured.

In **System / Configuration / Shipping Methods / General**, you can _hide and clear configuration_ for a custom selection of unused shipping methods. You are seeing a `*` in section head? This is a mark to inform you that the shipping method is available for the default country of the current store view.

In **System / Configuration / Customer / Address Templates**, templates are managed everywhere automatically with _adamlc/address-format_, phone numbers are formatted with _giggsey/libphonenumber-for-php_.

## New shipping methods (pick up)

Shipping methods are available for a selection of countries, depending on order weight and amount. All details are displayed in shipping methods configuration (you can also read content of `<default>` tag in *config.xml*).

![Screenshot](images/config.png?raw=true)

You will love debugging URLs for pick up shipping methods. You opened a link and you see the map while the shipping method is disabled but have a title? It's not a bug, it's a feature.

The lists of pick up points are retrieved regularly from internet (via a cron job) and saved in `var/shippingmax/*.dat`. When cron jobs are disabled, lists are retrieved on demand (when a customer open the map, if the cache file doesn't exist or if the cache file has expired).

The country of the customer shipping address is used on the map, and can't be changed on the map.

This module doesn't generate any labels.

Demo links may not work, don't panic, it's not a bug.

| Name | Logo/Link | Info |
| ---- | ---- | ---- |
| **Chrono Relais** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-chronopost.svg?raw=true" alt="" width="150" height="50"/>](https://www.chronopost.fr/fr/livraison/nos-offres/chrono-relais) | [online demo](https://cellu.blue/ef1sOP): 42100, Saint-Étienne, FR<br>api: account required |
| **Colis Privé** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-colisprive.svg?raw=true" alt="" width="150" height="50"/>](https://www.colisprive.fr/) | [online demo](https://cellu.blue/YSqL52): 42100, Saint-Étienne, FR<br>api: account required |
| **Mondial Relay** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-mondialrelay.svg?raw=true" alt="" width="150" height="50"/>](https://www.mondialrelay.fr/) | [online demo](https://cellu.blue/oNmaIV): 42100, Saint-Étienne, FR<br>api: account required |
| **DPD FR Relais** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-dpd.svg?raw=true" alt="" width="150" height="50"/>](https://www.dpd.fr/recherche-relais) | [online demo](https://cellu.blue/rlp2Ls): 38500, Voiron, FR<br>api: free |
| **InPost IT** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-inpost.svg?raw=true" alt="" width="150" height="50"/>](https://inpost.it/) | [online demo](https://cellu.blue/4K64Oc): 00121, Roma, IT<br>api: free |
| **InPost GB** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-inpost.svg?raw=true" alt="" width="150" height="50"/>](https://www.inpost.co.uk/) | [online demo](https://cellu.blue/ZQym8w): KA1 2QA, Kilmarnock, GB<br>api: free |
| **InPost PL** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-inpost.svg?raw=true" alt="" width="150" height="50"/>](https://inpost.pl/) | [online demo](https://cellu.blue/ZCWFMn): 22-100, Chełm, PL<br>api: free |
| **Pocztex** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-pocztex.svg?raw=true" alt="" width="150" height="50"/>](https://www.pocztex.pl/) | [online demo](https://cellu.blue/dPLFLI): 50-307, Wrocław, PL<br>api: free |
| **Packeta/Zásilkovna** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-packeta.svg?raw=true" alt="" width="150" height="50"/>](https://www.zasilkovna.cz/) | [online demo](https://cellu.blue/ub68fx): 547 01, Náchod, CZ<br>api: account required |
| **Boxberry** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-boxberry.svg?raw=true" alt="" width="150" height="50"/>](https://boxberry.ru/) | don't work with this country, it's an enemy of your freedom<br>[online demo](https://cellu.blue/bsIhKh): 664003, Иркутск, RU<br>api: free (demo token included with default configuration) |
| **5post** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-fivepost.svg?raw=true" alt="" width="150" height="50"/>](https://fivepost.ru/) | don't work with this country, it's an enemy of your freedom<br>[online demo](https://cellu.blue/6Y0hnT): 127299, Москва, RU<br>api: account required |
| **PickPoint** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-pickpoint.svg?raw=true" alt="" width="150" height="50"/>](https://pickpoint.ru/) | don't work with this country, it's an enemy of your freedom<br>[online demo](https://cellu.blue/OMXVxH): 140000, Люберцы, RU<br>api: account required |
| **Shiptor** | [<img src="src/skin/frontend/base/default/images/kyrena/shippingmax/ic-logo-shiptor.svg?raw=true" alt="" width="150" height="50"/>](https://shiptor.ru/) | don't work with this country, it's an enemy of your freedom<br>[online demo](https://cellu.blue/3RhP6s): 454000, Челябинск, RU<br>api: free |
| **Store delivery** | | [online demo](https://cellu.blue/OmRX8p): 07200, Aubenas, FR<br>source of data: a TSV file |
| **Store locator** | | [online demo](https://cellu.blue/h9dhKJ)<br>source of data: a TSV file |

Do you want more? Contact us, perhaps we can work together to add new methods! For example: Colissimo, Relais Colis, Swiss Post...

## Customization

For one step checkout modules, you must edit your shipping methods template, for example with:
```php
<?php foreach ($shippingRateGroups as $code => $rates): ?>
	[...]
	<?php foreach ($rates as $rate): ?>
		[...]
		<?php if ($this->helper('shippingmax')->isSpecial($code)): ?>
			<?php echo Mage::getBlockSingleton('shippingmax/selected')
				->setTemplate('kyrena/shippingmax/selected.phtml')
				->setData('code', $code)
				->toHtml() ?>
		<?php else: ?>
			[...]
			<input type="radio" name="shipping_method"
				value="<?php echo $rate->getCode() ?>" ...
			[...]
		<?php endif ?>
		[...]
	<?php endforeach ?>
<?php endforeach ?>
```

To display delivery times, you must edit your template with:
```php
<?php $shippingDate = $this->helper('shippingmax')->getShippingDate($rate->getCode()) ?>
<?php if (!empty($shippingDate)): ?>
	<?php echo $shippingDate ?>
<?php endif ?>
```

## Copyright and Credits

- Current version: 2.4.1 (08/08/2022)
- Compatibility: OpenMage 19.x / 20.x / 21.x, PHP 7.2 / 7.3 / 7.4 / 8.0 / 8.1
- Client compatibility: Firefox 36+, Chrome 32+, Opera 19+, Edge 16+, Safari 9+
- Translations: English (en), French (fr-FR/fr-CA), German (de), Italian (it), Portuguese (pt-PT/pt-BR), Spanish (es) / Chinese (zh), Czech (cs), Dutch (nl), Greek (el), Hungarian (hu), Japanese (ja), Polish (pl), Romanian (ro), Russian (ru), Slovak (sk), Turkish (tr), Ukrainian (uk)
- License: GNU GPL 2+

If you like, take some of your time to improve the translations, go to https://bit.ly/2HyCCEc.

## Installation

Warning: there are two packages, one that contains a lite and modified version (without jquery/editor/phpparser/doc) of owebia/shipping ([kyrena/openmage-shippingmax](https://github.com/kyrena/openmage-shippingmax)), and another one without owebia/shipping ([kyrena/openmage-shippingmax-alone](https://github.com/kyrena/openmage-shippingmax-alone)).

#### For kyrena/openmage-shippingmax

With composer:
- `composer remove owebia/magento1-module-advanced-shipping`
- search and remove all owebia/shipping files and directories
- `composer require kyrena/openmage-shippingmax [--ignore-platform-reqs]`
- clear cache

Without composer:
- search and remove all owebia/shipping files and directories
- download latest [release](https://github.com/kyrena/openmage-shippingmax/releases) and extract _src/*_ directories
- here _adamlc/address-format_, _giggsey/libphonenumber-for-php_ and _azuyalabs/yasumi_ are not required and not used, so I suggest you remove `<customer>...</customer>` block in our _system.xml_
- clear cache
