<?php
/**
 * Copyright © 2008-2020 Owebia. All rights reserved.
 * Copyright © 2019-2023 Kyrena. All rights reserved.
 * See COPYING.txt for license details.
 */

class Owebia_Shipping2_Model_Os2_Data_AddressFilter extends Owebia_Shipping2_Model_Os2_Data_Abstract
{
    protected static $_countries;

    // source : geonames.org, 2012-09-26
    protected static $_shortcuts = [
        // continents
        'AF' => [
            'label' => 'Africa',
            'replace' => [
                'AO', 'BF', 'BI', 'BJ', 'BW', 'CD', 'CF', 'CG', 'CI', 'CM', 'CV', 'DJ', 'DZ', 'EG', 'EH', 'ER', 'ET',
                'GA', 'GH', 'GM', 'GN', 'GQ', 'GW', 'KE', 'KM', 'LR', 'LS', 'LY', 'MA', 'MG', 'ML', 'MR', 'MU', 'MW',
                'MZ', 'NA', 'NE', 'NG', 'RE', 'RW', 'SC', 'SD', 'SS', 'SH', 'SL', 'SN', 'SO', 'ST', 'SZ', 'TD', 'TG',
                'TN', 'TZ', 'UG', 'YT', 'ZA', 'ZM', 'ZW',
            ],
        ],
        'AS' => [
            'label' => 'Asia',
            'replace' => [
                'AE', 'AF', 'AM', 'AZ', 'BD', 'BH', 'BN', 'BT', 'CC', 'CN', 'CX', 'GE', 'HK', 'ID', 'IL', 'IN', 'IO',
                'IQ', 'IR', 'JO', 'JP', 'KG', 'KH', 'KP', 'KR', 'KW', 'KZ', 'LA', 'LB', 'LK', 'MM', 'MN', 'MO', 'MV',
                'MY', 'NP', 'OM', 'PH', 'PK', 'PS', 'QA', 'SA', 'SG', 'SY', 'TH', 'TJ', 'TM', 'TR', 'TW', 'UZ', 'VN',
                'YE',
            ],
        ],
        'EU' => [
            'label' => 'Europa',
            'replace' => [
                'AD', 'AL', 'AT', 'AX', 'BA', 'BE', 'BG', 'BY', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FO',
                'FR', 'GB', 'GG', 'GI', 'GR', 'HR', 'HU', 'IE', 'IM', 'IS', 'IT', 'JE', 'XK', 'LI', 'LT', 'LU', 'LV',
                'MC', 'MD', 'ME', 'MK', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'RU', 'SE', 'SI', 'SJ', 'SK', 'SM',
                'UA', 'VA', 'CS',
            ],
        ],
        'NA' => [
            'label' => 'North America',
            'replace' => [
                'AG', 'AI', 'AW', 'BB', 'BL', 'BM', 'BQ', 'BS', 'BZ', 'CA', 'CR', 'CU', 'CW', 'DM', 'DO', 'GD', 'GL',
                'GP', 'GT', 'HN', 'HT', 'JM', 'KN', 'KY', 'LC', 'MF', 'MQ', 'MS', 'MX', 'NI', 'PA', 'PM', 'PR', 'SV',
                'SX', 'TC', 'TT', 'US', 'VC', 'VG', 'VI', 'AN',
            ],
        ],
        'SA' => [
            'label' => 'South America',
            'replace' => [
                'AR', 'BO', 'BR', 'CL', 'CO', 'EC', 'FK', 'GF', 'GY', 'PE', 'PY', 'SR', 'UY', 'VE',
            ],
        ],
        'OC' => [
            'label' => 'Oceania',
            'replace' => [
                'AS', 'AU', 'CK', 'FJ', 'FM', 'GU', 'KI', 'MH', 'MP', 'NC', 'NF', 'NR', 'NU', 'NZ', 'PF', 'PG', 'PN',
                'PW', 'SB', 'TK', 'TL', 'TO', 'TV', 'UM', 'VU', 'WF', 'WS',
            ],
        ],
        'AN' => [
            'label' => 'Antartica',
            'replace' => ['AQ', 'BV', 'GS', 'HM', 'TF'],
        ],
        'EU-27' => [
            'label' => 'European Union',
            'replace' => [
                'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HU', 'IE', 'IT', 'LT', 'LU',
                'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SI', 'SK', 'SE', 'GB',
            ],
        ],
        /* Guadeloupe, Martinique, Guyane, Réunion, Mayotte */
        'DOM' => [
            'label' => "Département d'Outre-Mer",
            'replace' => ['GP', 'MQ', 'GF', 'RE', 'YT'],
        ],
        /* Polynésie française, Saint-Pierre-et-Miquelon, Wallis-et-Futuna, Saint-Martin, Saint-Barthélemy, Nouvelle-Calédonie */
        'COM' => [
            'label' => "Collectivités d'Outre-Mer",
            'replace' => ['PF', 'PM', 'WF', 'MF', 'BL', 'NC'],
        ],
    ];

    public static function readable($input)
    {
        if (!self::$_countries) {
            $collection = Mage::getModel('directory/country')->getCollection();
            $countries = [];
            foreach ($collection as $country) {
                $countries[$country->getId()] = $country->getName();
            }
            self::$_countries = $countries;
        }

        $elems = preg_split('/\b/', $input);
        $output = '';
        foreach ($elems as $elem) {
            $output .= self::$_countries[$elem] ?? $elem;
        }
        while (preg_match('/{address_filter\.([^}]+)}/', $output, $result)) {
            $name = $result[1];
            $replacement = isset(self::$_shortcuts[$name]) ? self::$_shortcuts[$name]['label'] : 'unknown';
            $replacement = Mage::helper('owebia_shipping2')->__($replacement);
            $output = str_replace($result[0], $replacement, $output);
        }
        return $output;
    }

    protected function _load($name)
    {
        if (isset(self::$_shortcuts[$name])) {
            return implode(',', self::$_shortcuts[$name]['replace']);
        }
        return parent::_load($name);
    }
}