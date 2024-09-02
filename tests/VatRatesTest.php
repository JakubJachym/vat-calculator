<?php
declare(strict_types = 1);

namespace JakubJachym\VatCalculator;

use DateTimeImmutable;
use JakubJachym\VatCalculator\Exceptions\NoVatRulesForCountryException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class VatRatesTest extends TestCase
{

	/** @var VatRates */
	private $vatRates;


	protected function setUp(): void
	{
		$this->vatRates = new VatRates();
	}


	public function testAddRateKnownCountry(): void
	{
		$country = 'nO';
		$this->assertFalse($this->vatRates->shouldCollectVat($country));
		$this->assertEquals(0, $this->vatRates->getTaxRateForLocation($country, null));
		$this->vatRates->addRateForCountry($country);
		$this->assertTrue($this->vatRates->shouldCollectVat($country));
		$this->assertEquals(0.25, $this->vatRates->getTaxRateForLocation($country, null));
	}


	public function testAddRateUnknownCountry(): void
	{
		$country = 'yEs';
		$this->assertFalse($this->vatRates->shouldCollectVat($country));
		$this->assertEquals(0, $this->vatRates->getTaxRateForLocation($country, null));
		$this->expectException(NoVatRulesForCountryException::class);
		$this->vatRates->addRateForCountry($country);
		$this->assertFalse($this->vatRates->shouldCollectVat($country));
		$this->assertEquals(0, $this->vatRates->getTaxRateForLocation($country, null));
	}


	public function testGetRatesSince(): void
	{
		$class = new ReflectionClass($this->vatRates);
		$property = $class->getProperty('now');
		$property->setAccessible(true);

		$date = '2020-06-30 23:59:59 Europe/Berlin';
		$property->setValue($this->vatRates, new DateTimeImmutable($date));
		$this->assertEquals(0.19, $this->vatRates->getTaxRateForLocation('DE', null));
		$this->assertEquals(0.19, $this->vatRates->getTaxRateForLocation('DE', null, VatRates::GENERAL, new DateTimeImmutable($date)));

		$date = '2020-07-01 00:00:00 Europe/Berlin';
		$property->setValue($this->vatRates, new DateTimeImmutable($date));
		$this->assertEquals(0.16, $this->vatRates->getTaxRateForLocation('DE', null));
		$this->assertEquals(0.16, $this->vatRates->getTaxRateForLocation('DE', null, VatRates::GENERAL, new DateTimeImmutable($date)));

		$date = '2021-01-01 00:00:00 Europe/Berlin';
		$property->setValue($this->vatRates, new DateTimeImmutable($date));
		$this->assertEquals(0.19, $this->vatRates->getTaxRateForLocation('DE', null));
		$this->assertEquals(0.19, $this->vatRates->getTaxRateForLocation('DE', null, VatRates::GENERAL, new DateTimeImmutable($date)));
	}


	public function testGetRatesExceptionsItLivigno(): void
	{
		$this->assertEquals(0, $this->vatRates->getTaxRateForLocation('IT', '23041'));
	}


	public function testGetRatesForLuxembourg(): void
	{
		$this->assertEquals(0.17, $this->vatRates->getTaxRateForLocation('LU', null));
		$this->assertEquals(0.17, $this->vatRates->getTaxRateForLocation('LU', null, VatRates::STANDARD_RATE));
		$this->assertEquals(0.08, $this->vatRates->getTaxRateForLocation('LU', null, VatRates::REDUCED_RATE));
		$this->assertEquals(0.03, $this->vatRates->getTaxRateForLocation('LU', null, VatRates::SUPER_REDUCED_RATE));
		$this->assertEquals(0.14, $this->vatRates->getTaxRateForLocation('LU', null, VatRates::PARKING_RATE));
	}


	public function testGetAllKnownRates(): void
	{
		$this->assertEquals([0.20, 0.10, 0.13, 0.05, 0.19], $this->vatRates->getAllKnownRates('AT'));
		$this->assertEquals([0.21, 0.12], $this->vatRates->getAllKnownRates('CZ'));
		$this->assertEquals([0.19, 0, 0.07, 0.16], $this->vatRates->getAllKnownRates('DE'));
		$this->assertEquals([0.21, 0.09], $this->vatRates->getAllKnownRates('NL'));
	}

}
