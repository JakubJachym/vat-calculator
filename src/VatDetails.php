<?php
declare(strict_types = 1);

namespace JakubJachym\VatCalculator;

class VatDetails
{

	public function __construct(private bool $valid, private string $countryCode, private string $vatNumber, private ?string $requestId)
	{
	}


	public function isValid(): bool
	{
		return $this->valid;
	}


	public function getCountryCode(): string
	{
		return $this->countryCode;
	}


	public function getVatNumber(): string
	{
		return $this->vatNumber;
	}


	public function getRequestId(): ?string
	{
		return $this->requestId;
	}

}
