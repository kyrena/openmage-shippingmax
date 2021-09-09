<?php
/**
 * Created V/12/04/2019
 * Updated J/05/08/2021
 *
 * Copyright 2019-2021 | Fabrice Creuzot <fabrice~cellublue~com>
 * Copyright 2019-2021 | Jérôme Siau <jerome~cellublue~com>
 * https://github.com/kyrena/openmage-shippingmax
 *
 * This program is free software, you can redistribute it or modify
 * it under the terms of the GNU General Public License (GPL) as published
 * by the free software foundation, either version 2 of the license, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but without any warranty, without even the implied warranty of
 * merchantability or fitness for a particular purpose. See the
 * GNU General Public License (GPL) for more details.
 */

class Kyrena_Shippingmax_Model_Carrier_Mondialrelay extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_mondialrelay';
	protected $_full = false;
	protected $_api  = true;

	public function loadItemsFromApi(object $address, bool $ignoreLatLng = false) {

		$items = [];
		ini_set('default_socket_timeout', 20);

		// https://www.mondialrelay.fr/solutionspro/documentation-technique/cahier-des-charges-informatiques/
		// https://www.mondialrelay.fr/media/108937/Solution-Web-Service-V5.6.pdf
		try {
			$client = new SoapClient($this->getConfigData('api_url'), ['trace' => 1]);
			$params = [
				'Enseigne'  => $this->getConfigData('api_username'),
				'Pays'      => ($address->getData('country_id') == 'MC') ? 'FR' : $address->getData('country_id'),
				'CP'        => trim(is_numeric($address->getData('city')) ? $address->getData('city') : $address->getData('postcode')),
				'Latitude'  => number_format(round($address->getData('lat'), 11), 6),
				'Longitude' => number_format(round($address->getData('lng'), 11), 6),
				'RayonRecherche' => $this->getConfigData('dst_search')
			];

			// géolocalisation navigateur ou coordonnées nominatim
			if ($ignoreLatLng || empty($params['Latitude']) || empty($params['Longitude']))
				unset($params['Latitude'], $params['Longitude']);
			else
				unset($params['CP']);

			$params['Security'] = mb_strtoupper(md5(implode('', $params).
				$this->getConfigData('api_password', true)));

			$results = $client->WSI4_PointRelais_Recherche($params)->WSI4_PointRelais_RechercheResult;
		}
		catch (Throwable $t) {
			Mage::logException($t);
		}

		ini_restore('default_socket_timeout');
		if (empty($results) || !is_object($results))
			return $items;

		if (!$ignoreLatLng && in_array($results->STAT, [67, 68])) {
			// géolocalisation navigateur ou coordonnées nominatim
			// si latitude ou longitude invalide réessaye sans
			return $this->loadItemsFromApi($address, true);
		}

		//echo '<pre>';print_r(array_slice($results->PointsRelais->PointRelais_Details, 0, 20));exit;
		if (($results->STAT == 0) && !empty($results->PointsRelais->PointRelais_Details)) {

			foreach ($results->PointsRelais->PointRelais_Details as $result) {

				if (empty($result->Num))
					continue;

				$items[$result->Num] = [
					'id'          => $result->Num,
					'lat'         => trim(str_replace(',', '.', $result->Latitude), '0'),
					'lng'         => trim(str_replace(',', '.', $result->Longitude), '0'),
					'name'        => $result->LgAdr1,
					'street'      => implode("\n", array_filter([$result->LgAdr2, $result->LgAdr3, $result->LgAdr4])),
					'postcode'    => $result->CP,
					'city'        => $result->Ville,
					'country_id'  => $result->Pays,
					'description' => $this->getDesc($result),
					'dst'         => round($result->Distance / 1000, 1)
				];
			}
		}

		return $items;
	}

	protected function checkIfAvailable(object $request) {

		// France (avec la Corse et Monaco - sans les DROM/COM)
		// 05/2019 https://www.mondialrelay.fr/faq/envoyer-un-colis/faites-vous-des-livraisons-dans-les-dom-tom-/
		if (in_array($request->getData('country_id'), Mage::helper('shippingmax')->getFranceDromCom())) {

			$postcode = trim($request->getData('dest_postcode'));
			// FR France
			// FR 20XXX Corse
			// MC 980XX Monaco
			//  DROM
			// GP 971XX Guadeloupe
			// MQ 972XX Martinique
			// GF 973XX Guyane
			// RE 974XX La Réunion
			// YT 976XX Mayotte
			//  COM
			// BL 97133 Saint-Barthélemy (977 Antilles)
			// MF 97150 Saint-Martin     (978 Antilles)
			// PM 975XX Saint-Pierre-et-Miquelon
			// WF 986XX Wallis-et-Futuna
			// PF 987XX Polynésie Française
			// NC 988XX Nouvelle-Calédonie
			//  TOM
			// TF 984XX Terres australes françaises
			if (mb_stripos($postcode, '97') === 0)
				return false;
			if ((mb_stripos($postcode, '98') === 0) && (mb_stripos($postcode, '980') === false))
				return false;
		}
		// Espagne (avec les Îles Baléares - sans les Îles Canaries, Ceuta et Melilla)
		// 05/2019 https://www.puntopack.es/preguntas-frecuentes/enviar-un-paquete/entregas-en-las-islas-o-enclaves/
		else if ($request->getData('country_id') == 'ES') {

			$postcode = trim($request->getData('dest_postcode'));
			// ES 07XXX Baleares (AUTORISÉ)
			// ES 35XXX Las Palmas (Canaries)
			// ES 38XXX Santa Cruz de Tenerife (Canaries)
			// ES 51XXX Ceuta
			// ES 52XXX Melilla
			if (mb_stripos($postcode, '35') === 0)
				return false;
			if (mb_stripos($postcode, '38') === 0)
				return false;
			if (mb_stripos($postcode, '51') === 0)
				return false;
			if (mb_stripos($postcode, '52') === 0)
				return false;
		}

		return parent::checkIfAvailable($request);
	}

	private function getDesc($data) {

		$html = [];
		$days = [
			'1 Monday'    => $data->Horaires_Lundi->string,
			'2 Tuesday'   => $data->Horaires_Mardi->string,
			'3 Wednesday' => $data->Horaires_Mercredi->string,
			'4 Thursday'  => $data->Horaires_Jeudi->string,
			'5 Friday'    => $data->Horaires_Vendredi->string,
			'6 Saturday'  => $data->Horaires_Samedi->string,
			'7 Sunday'    => $data->Horaires_Dimanche->string
		];

		foreach ($days as $day => $str) {

			// fermé toute la journée
			if (($str[0] == '0000') && ($str[2] == '0000')) {
				$html[] = $day.'#closed';
			}
			// fermé le matin ou fermé l'après midi
			else if (($str[0] == '0000') && ($str[2] != '0000')) {
				$html[] = $day.'#'.
					substr($str[2], 0, 2).'#'.substr($str[2], 2, 2).'#'.
					substr($str[3], 0, 2).'#'.substr($str[3], 2, 2);
			}
			// ouvert non stop
			else if (($str[0] != '0000') && ($str[2] == '0000')) {
				$html[] = $day.'#'.
					substr($str[0], 0, 2).'#'.substr($str[0], 2, 2).'#'.
					substr($str[1], 0, 2).'#'.substr($str[1], 2, 2);
			}
			// fermé à midi
			else {
				$html[] = $day.'#'.
					substr($str[0], 0, 2).'#'.substr($str[0], 2, 2).'#'.
					substr($str[1], 0, 2).'#'.substr($str[1], 2, 2).'#'.
					substr($str[2], 0, 2).'#'.substr($str[2], 2, 2).'#'.
					substr($str[3], 0, 2).'#'.substr($str[3], 2, 2);
			}
		}

		return implode("\n", $html);
	}
}