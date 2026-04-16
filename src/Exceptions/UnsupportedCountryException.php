<?php
declare(strict_types = 1);

namespace JakubJachym\VatCalculator\Exceptions;

class UnsupportedCountryException extends VatNumberException
{

	public function __construct(string $countryCode)
	{
		parent::__construct('Unsupported/non-EU country ' . $countryCode);
	}

}
