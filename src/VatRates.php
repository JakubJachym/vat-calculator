<?php
declare(strict_types = 1);

namespace JakubJachym\VatCalculator;

use DateTimeImmutable;
use DateTimeInterface;
use JakubJachym\VatCalculator\Exceptions\NoVatRulesForCountryException;

/**
 * @phpstan-type CountryTaxRules array{rate: float, rates?: array<string, float>, exceptions?: array<string, float|array<string|null, float>>, since?: array<string, array{rate: float, rates?: array<string, float>}>}
 * @phpstan-type PostalCodeTaxExceptions array<string, array<int, array{postalCode: string, code: string, name?: string, city?: string}>>
 */
class VatRates
{
	public const null GENERAL = null;
	public const string STANDARD_RATE = "standard";
	public const string REDUCED_RATE = "reduced";
	public const string REDUCED_2ND_RATE = "reduced-second";
	public const string SUPER_REDUCED_RATE = "super-reduced";
	public const string PARKING_RATE = "parking";

	// Kept for backwards compatibility
	public const string HIGH = self::STANDARD_RATE;
	public const string LOW = self::REDUCED_RATE;

	/**
	 * All available tax rules and their exceptions.
	 *
	 * Taken from: https://taxation-customs.ec.europa.eu/document/download/28091aa6-62e0-4b27-922c-4f701e2f0ce3_en?filename=vat_rates_en.pdf
	 *
	 * @var array<string, CountryTaxRules>
	 */
	private array $taxRules = [
		'AT' => [ // Austria - https://www.wko.at/steuern/umsatzsteuer-ueberblick-tabelle#heading_Steuers_tze
			'rate' => 0.20,
			'rates' => [
				self::STANDARD_RATE => 0.20,
				self::REDUCED_RATE => 0.10,
				self::REDUCED_2ND_RATE => 0.13,
				self::PARKING_RATE => 0.13,
			],
			'exceptions' => [
				'Jungholz' => 0.19,
				'Mittelberg' => 0.19,
			],
		],
		'BE' => [ // Belgium
			'rate' => 0.21,
			'rates' => [
				self::STANDARD_RATE => 0.21,
				self::REDUCED_RATE => 0.06,
				self::PARKING_RATE => 0.12,
			],
		],
		'BG' => [ // Bulgaria
			'rate' => 0.20,
			'rates' => [
				self::STANDARD_RATE => 0.20,
				self::REDUCED_RATE => 0.09,
			],
		],
		'CY' => [ // Cyprus
			'rate' => 0.19,
			'rates' => [
				self::STANDARD_RATE => 0.19,
				self::REDUCED_RATE => 0.05,
				self::REDUCED_2ND_RATE => 0.09,
			],
		],
		'CZ' => [ // Czech Republic
			'rate' => 0.21,
			'rates' => [
				self::STANDARD_RATE => 0.21,
				self::REDUCED_RATE => 0.12,
			],
		],
		'DE' => [ // Germany
			'rate' => 0.19,
			'rates' => [
				self::STANDARD_RATE => 0.19,
				self::REDUCED_RATE => 0.07,
			],
			'exceptions' => [
				'Heligoland' => 0,
				'Büsingen am Hochrhein' => 0,
			],
		],
		'DK' => [ // Denmark
			'rate' => 0.25,
		],
		'EE' => [ // Estonia - https://www.emta.ee/en/business-client/taxes-and-payment/value-added-tax/vat-rates-and-supply-exempt-tax
			'rate' => 0.24,
			'rates' => [
				self::STANDARD_RATE => 0.24,
				self::REDUCED_RATE => 0.13,
				self::REDUCED_2ND_RATE => 0.09,
			],
		],
		'EL' => [ // Hellenic Republic (Greece)
			'rate' => 0.24,
			'rates' => [
				self::STANDARD_RATE => 0.24,
				self::REDUCED_RATE => 0.13,
				self::REDUCED_2ND_RATE => 0.06,
			],
			'exceptions' => [
				'Mount Athos' => 0,
			],
		],
		'ES' => [ // Spain - https://administracion.gob.es/pag_Home/en/Tu-espacio-europeo/derechos-obligaciones/empresas/impuestos/IVA/tipos-exenciones.html
			'rate' => 0.21,
			'rates' => [
				self::STANDARD_RATE => 0.21,
				self::REDUCED_RATE => 0.10,
				self::REDUCED_2ND_RATE => 0.04,
			],
			'exceptions' => [
				'Canary Islands' => 0,
				'Ceuta' => 0,
				'Melilla' => 0,
			],
		],
		'FI' => [ // Finland - https://vm.fi/en/value-added-tax
			'rate' => 0.255,
			'rates' => [
				self::STANDARD_RATE => 0.255,
				self::REDUCED_RATE => 0.135,
				self::REDUCED_2ND_RATE => 0.10,
			],
		],
		'FR' => [ // France
			'rate' => 0.20,
			'rates' => [
				self::STANDARD_RATE => 0.20,
				self::REDUCED_RATE => 0.055,
				self::REDUCED_2ND_RATE => 0.10,
				self::SUPER_REDUCED_RATE => 0.021,
			],
			'exceptions' => [
				// Overseas France
				'Reunion' => 0.085,
				'Martinique' => 0.085,
				'Guadeloupe' => 0.085,
				'Guyane' => 0,
				'Mayotte' => 0,
			],
		],
		'GR' => [ // Greece
			'rate' => 0.24,
			'rates' => [
				self::STANDARD_RATE => 0.24,
				self::REDUCED_RATE => 0.13,
				self::REDUCED_2ND_RATE => 0.06,
			],
			'exceptions' => [
				'Mount Athos' => 0,
			],
		],
		'HR' => [ // Croatia
			'rate' => 0.25,
			'rates' => [
				self::STANDARD_RATE => 0.25,
				self::REDUCED_RATE => 0.05,
				self::REDUCED_2ND_RATE => 0.13,
			],
		],
		'HU' => [ // Hungary
			'rate' => 0.27,
			'rates' => [
				self::STANDARD_RATE => 0.27,
				self::REDUCED_RATE => 0.05,
				self::REDUCED_2ND_RATE => 0.18,
			],
		],
		'IE' => [ // Ireland
			'rate' => 0.23,
			'rates' => [
				self::STANDARD_RATE => 0.23,
				self::REDUCED_RATE => 0.135,
				self::REDUCED_2ND_RATE => 0.09,
				self::SUPER_REDUCED_RATE => 0.048,
			],
		],
		'IT' => [ // Italy
			'rate' => 0.22,
			'rates' => [
				self::STANDARD_RATE => 0.22,
				self::REDUCED_RATE => 0.05,
				self::REDUCED_2ND_RATE => 0.10,
				self::SUPER_REDUCED_RATE => 0.04,
			],
			'exceptions' => [
				'Campione d\'Italia' => 0,
				'Livigno' => 0,
			],
		],
		'LT' => [ // Lithuania
			'rate' => 0.21,
			'rates' => [
				self::STANDARD_RATE => 0.21,
				self::REDUCED_RATE => 0.05,
				self::REDUCED_2ND_RATE => 0.09,
			],
		],
		'LU' => [ // Luxembourg
			'rate' => 0.17,
			'rates' => [
				self::STANDARD_RATE => 0.17,
				self::REDUCED_RATE => 0.08,
				self::SUPER_REDUCED_RATE => 0.03,
				self::PARKING_RATE => 0.14,
			],
		],
		'LV' => [ // Latvia
			'rate' => 0.21,
		],
		'MT' => [ // Malta
			'rate' => 0.18,
		],
		'NL' => [ // Netherlands
			'rate' => 0.21,
			'rates' => [
				self::STANDARD_RATE => 0.21,
				self::REDUCED_RATE => 0.09,
			],
		],
		'PL' => [ // Poland
			'rate' => 0.23,
		],
		'PT' => [ // Portugal
			'rate' => 0.23,
			'exceptions' => [
				'Azores' => 0.16,
				'Madeira' => 0.22,
			],
		],
		'RO' => [ // Romania
			'rate' => 0.21,
			'rates' => [
				self::STANDARD_RATE => 0.21,
				self::REDUCED_RATE => 0.11,
			],
		],
		'SE' => [ // Sweden
			'rate' => 0.25,
		],
		'SI' => [ // Slovenia
			'rate' => 0.22,
		],
		'SK' => [ // Slovakia
			'rate' => 0.23,
			'rates' => [
				self::STANDARD_RATE => 0.23,
				self::REDUCED_RATE => 0.19,
				self::REDUCED_2ND_RATE => 0.05,
			],
		],

		// Countries associated with EU countries that have a special VAT rate
		'MC' => [ // Monaco France
			'rate' => 0.20,
		],
		'IM' => [ // Isle of Man - United Kingdom
			'rate' => 0.20,
		],
	];

	/**
	 * Optional tax rules.
	 *
	 * Non-EU countries with their own VAT requirements, countries in this list
	 * need to be added manually by `addRateForCountry()` for the rate to be applied.
	 *
	 * @var array<string, CountryTaxRules>
	 */
	private array $optionalTaxRules = [
		'CH' => [ // Switzerland
			'rate' => 0.081,
			'rates' => [
				self::STANDARD_RATE => 0.081,
				self::REDUCED_RATE => 0.026,
				self::SUPER_REDUCED_RATE => 0.038,
			],
		],
		'GB' => [ // United Kingdom
			'rate' => 0.20,
			'exceptions' => [
				// UK RAF Bases in Cyprus are taxed at Cyprus rate
				'Akrotiri' => 0.19,
				'Dhekelia' => 0.19,
			],
		],
		'NO' => [ // Norway
			'rate' => 0.25,
		],
		'TR' => [ // Turkey
			'rate' => 0.18,
		],
	];

	/**
	 * All possible postal code exceptions.
	 *
	 * @var PostalCodeTaxExceptions
	 */
	private array $postalCodeExceptions = [
		'AT' => [
			[
				'postalCode' => '/^6691$/',
				'code' => 'AT',
				'name' => 'Jungholz',
			],
			[
				'postalCode' => '/^699[123]$/',
				'city' => '/\bmittelberg\b/i',
				'code' => 'AT',
				'name' => 'Mittelberg',
			],
		],
		'CH' => [
			[
				'postalCode' => '/^8238$/',
				'code' => 'DE',
				'name' => 'Büsingen am Hochrhein',
			],
			// The Italian city of Domodossola has a Swiss post office also
			[
				'postalCode' => '/^3907$/',
				'code' => 'IT',
			],
		],
		'DE' => [
			[
				'postalCode' => '/^87491$/',
				'code' => 'AT',
				'name' => 'Jungholz',
			],
			[
				'postalCode' => '/^8756[789]$/',
				'city' => '/\bmittelberg\b/i',
				'code' => 'AT',
				'name' => 'Mittelberg',
			],
			[
				'postalCode' => '/^78266$/',
				'code' => 'DE',
				'name' => 'Büsingen am Hochrhein',
			],
			[
				'postalCode' => '/^27498$/',
				'code' => 'DE',
				'name' => 'Heligoland',
			],
		],
		'ES' => [
			[
				'postalCode' => '/^(5100[1-5]|5107[0-1]|51081)$/',
				'code' => 'ES',
				'name' => 'Ceuta',
			],
			[
				'postalCode' => '/^(5200[0-6]|5207[0-1]|52081)$/',
				'code' => 'ES',
				'name' => 'Melilla',
			],
			[
				'postalCode' => '/^(35\d{3}|38\d{3})$/',
				'code' => 'ES',
				'name' => 'Canary Islands',
			],
		],
		'FR' => [
			[
				'postalCode' => '/^971\d{2,}$/',
				'code' => 'FR',
				'name' => 'Guadeloupe',
			],
			[
				'postalCode' => '/^972\d{2,}$/',
				'code' => 'FR',
				'name' => 'Martinique',
			],
			[
				'postalCode' => '/^973\d{2,}$/',
				'code' => 'FR',
				'name' => 'Guyane',
			],
			[
				'postalCode' => '/^974\d{2,}$/',
				'code' => 'FR',
				'name' => 'Reunion',
			],
			[
				'postalCode' => '/^976\d{2,}$/',
				'code' => 'FR',
				'name' => 'Mayotte',
			],
		],
		'GR' => [
			[
				'postalCode' => '/^63086$/',
				'code' => 'GR',
				'name' => 'Mount Athos',
			],
		],
		'IT' => [
			[
				'postalCode' => '/^22061$/',
				'city' => '/\bcampione\b/i',
				'code' => 'IT',
				'name' => "Campione d'Italia",
			],
			[
				'postalCode' => '/^23041$/',
				'city' => '/\blivigno\b/i',
				'code' => 'IT',
				'name' => 'Livigno',
			],
		],
		'PT' => [
			[
				'postalCode' => '/^9[0-4]\d{2,}$/',
				'code' => 'PT',
				'name' => 'Madeira',
			],
			[
				'postalCode' => '/^9[5-9]\d{2,}$/',
				'code' => 'PT',
				'name' => 'Azores',
			],
		],
	];

	/**
	 * Optional postal code exceptions.
	 *
	 * Non-EU countries with their own VAT requirements and postal code exceptions,
	 * added with `addRateForCountry()` for the rate and the exceptions to be applied.
	 *
	 * @var PostalCodeTaxExceptions
	 */
	private array $optionalPostalCodeExceptions = [
		'GB' => [
			// Akrotiri
			[
				'postalCode' => '/^BFPO57|BF12AT$/',
				'code' => 'CY',
			],
			// Dhekelia
			[
				'postalCode' => '/^BFPO58|BF12AU$/',
				'code' => 'CY',
			],
		],
	];

	/** @var DateTimeImmutable */
	private DateTimeImmutable $now;


	public function __construct()
	{
		$this->now = new DateTimeImmutable();
	}


	/**
	 * @param string $country
	 * @throws NoVatRulesForCountryException
	 */
	public function addRateForCountry(string $country): void
	{
		$country = strtoupper($country);
		if (!isset($this->optionalTaxRules[$country])) {
			throw new NoVatRulesForCountryException("No optional tax rules specified for $country");
		}
		$this->taxRules[$country] = $this->optionalTaxRules[$country];
		if (isset($this->optionalPostalCodeExceptions[$country])) {
			$this->postalCodeExceptions[$country] = $this->optionalPostalCodeExceptions[$country];
		}
	}


	public function shouldCollectVat(string $countryCode): bool
	{
		return isset($this->taxRules[strtoupper($countryCode)]);
	}


	public function shouldCollectEuVat(string $countryCode): bool
	{
		$countryCode = strtoupper($countryCode);
		return isset($this->taxRules[$countryCode]) && !isset($this->optionalTaxRules[$countryCode]);
	}


	/**
	 * Returns the tax rate for the given country code.
	 * If a postal code is provided, it will try to lookup the different
	 * postal code exceptions that are possible. Specify a date to use VAT rate valid for that date.
	 *
	 * @param string $countryCode
	 * @param string|null $postalCode
	 * @param string|null $type
	 * @param DateTimeInterface|null $date Date to use the VAT rate for, null for current date
	 * @return float
	 */
	public function getTaxRateForLocation(string $countryCode, ?string $postalCode, ?string $type = self::GENERAL, ?DateTimeInterface $date = null): float
	{
		$countryCode = strtoupper($countryCode);
		if (isset($this->postalCodeExceptions[$countryCode]) && $postalCode !== null) {
			foreach ($this->postalCodeExceptions[$countryCode] as $postalCodeException) {
				if (!preg_match($postalCodeException['postalCode'], $postalCode)) {
					continue;
				}
				if (isset($postalCodeException['name'], $this->taxRules[$postalCodeException['code']]['exceptions'])) {
					$rules = $this->taxRules[$postalCodeException['code']]['exceptions'][$postalCodeException['name']];
					if (!is_array($rules)) {
						return $rules;
					}
					if ($type !== VatRates::GENERAL && isset($rules['rates'][$type]) && is_float($rules['rates'][$type])) {
						return $rules['rates'][$type];
					}

					return $rules['rate'];
				}
				return $this->getRules($postalCodeException['code'], $date)['rate'];
			}
		}

		$rules = $this->getRules($countryCode, $date);
		if ($type !== VatRates::GENERAL) {
			if (isset($rules['rates'])) {
				return $rules['rates'][$type];
			}
		}

		return $rules['rate'];
	}


	/**
	 * @param string $countryCode
	 * @param DateTimeInterface|null $date
	 * @return array{rate: float, rates?: array<string, float>}
	 */
	private function getRules(string $countryCode, ?DateTimeInterface $date = null): array
	{
		if (!isset($this->taxRules[$countryCode])) {
			return ['rate' => 0];
		}
		if (isset($this->taxRules[$countryCode]['since'])) {
			foreach ($this->taxRules[$countryCode]['since'] as $since => $rates) {
				if (new DateTimeImmutable($since) <= ($date ?? $this->now)) {
					return $rates;
				}
			}
		}
		return $this->taxRules[$countryCode];
	}


	/**
	 * Get an array of all known VAT rates for given country.
	 *
	 * Returns current rate, high & low rates, historical & future rates, exceptions, unsorted.
	 *
	 * @param string $country
	 * @return array<int, float>
	 */
	public function getAllKnownRates(string $country): array
	{
		if (!isset($this->taxRules[$country])) {
			return [];
		}

		$rates = $this->getRates($this->taxRules[$country]);
		if (isset($this->taxRules[$country]['since'])) {
			foreach ($this->taxRules[$country]['since'] as $sinceRules) {
				$rates = array_merge($rates, $this->getRates($sinceRules));
			}
		}
		return array_values(array_unique($rates));
	}


	/**
	 * @param CountryTaxRules $taxRules
	 * @return array<int, float>
	 */
	private function getRates(array $taxRules): array
	{
		$rates = [$taxRules['rate']];
		if (isset($taxRules['rates'])) {
			foreach ($taxRules['rates'] as $rate) {
				$rates[] = $rate;
			}
		}
		if (isset($taxRules['exceptions'])) {
			foreach ($taxRules['exceptions'] as $exceptions) {
				foreach ((array)$exceptions as $exception) {
					$rates[] = $exception;
				}
			}
		}
		return $rates;
	}

}
