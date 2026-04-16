<?php
declare(strict_types = 1);

namespace JakubJachym\VatCalculator;

class VatPrice
{

	public function __construct(private readonly float $netPrice, private readonly float $price, private readonly float $taxValue, private readonly float $taxRate)
	{
	}


	public function getNetPrice(): float
	{
		return $this->netPrice;
	}


	public function getPrice(): float
	{
		return $this->price;
	}


	public function getTaxValue(): float
	{
		return $this->taxValue;
	}


	public function getTaxRate(): float
	{
		return $this->taxRate;
	}

}
