<?php
declare(strict_types = 1);

namespace JakubJachym\VatCalculator;

use DateTimeImmutable;
use JakubJachym\VatCalculator\Exceptions\InvalidCharsInVatNumberException;
use JakubJachym\VatCalculator\Exceptions\UnsupportedCountryException;
use JakubJachym\VatCalculator\Exceptions\VatCheckUnavailableException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SoapClient;
use SoapFault;
use stdClass;

class VatCalculatorTest extends TestCase
{

	private const DATE = '2020-06-30 23:59:59 Europe/Berlin';

	/** @var VatCalculator */
	private $vatCalculator;

	/** @var SoapClient */
	private $vatCheck;

	/** @var VatRates */
	private $vatRates;


	protected function setUp(): void
	{
		$this->vatRates = new VatRates();
		$this->vatCalculator = new VatCalculator($this->vatRates);
		$this->vatCheck = $this->getMockFromWsdl(__DIR__ . '/checkVatService.wsdl', 'VatService');

		$class = new ReflectionClass($this->vatRates);
		$property = $class->getProperty('now');
		$property->setAccessible(true);
		$property->setValue($this->vatRates, new DateTimeImmutable(self::DATE));
	}


	public function testCalculateVat(): void
	{
		$result = $this->vatCalculator->calculate(24.00, 'DE', null, false);
		$this->assertEquals(28.56, round($result->getPrice(), 2));
		$this->assertEquals(0.19, $result->getTaxRate());
		$this->assertEquals(4.56, round($result->getTaxValue(), 2));

		$result = $this->vatCalculator->calculate(24.00, 'DE', null, true);
		$this->assertEquals(24.00, $result->getPrice());
		$this->assertEquals(0, $result->getTaxRate());
		$this->assertEquals(0, $result->getTaxValue());

		$result = $this->vatCalculator->calculate(24.00, 'XXX', null, true);
		$this->assertEquals(24.00, $result->getPrice());
		$this->assertEquals(0.00, $result->getTaxRate());
		$this->assertEquals(0.00, $result->getTaxValue());

		$result = $this->vatCalculator->calculate(24.00, 'DE', null, false, VatRates::GENERAL, new DateTimeImmutable(self::DATE));
		$this->assertEquals(28.56, round($result->getPrice(), 2));
	}


	public function testGetTaxRateForLocation(): void
	{
		$result = $this->vatCalculator->getTaxRateForLocation('DE', null, false);
		$this->assertEquals(0.19, $result);

		$result = $this->vatCalculator->getTaxRateForLocation('DE', null, true);
		$this->assertEquals(0, $result);

		$result = $this->vatCalculator->getTaxRateForLocation('DE', null, false, VatRates::GENERAL, new DateTimeImmutable(self::DATE));
		$this->assertEquals(0.19, $result);
	}


	public function testCanValidateValidVatNumber(): void
	{
		$result = new stdClass();
		$result->valid = true;
		$result->countryCode = 'DE';
		$result->vatNumber = '190098891';
		$result->requestIdentifier = null;

		$this->vatCheck->expects($this->any())
			->method('checkVatApprox')
			->with([
				'countryCode' => 'DE',
				'vatNumber' => '190098891',
				'requesterCountryCode' => null,
				'requesterVatNumber' => null,
			])
			->willReturn($result);

		$vatNumber = 'DE 190 098 891';
		$this->vatCalculator->setSoapClient($this->vatCheck);
		$result = $this->vatCalculator->isValidVatNumber($vatNumber);
		$this->assertTrue($result);
	}


	public function testCanValidateInvalidVatNumber(): void
	{
		$result = new stdClass();
		$result->valid = false;
		$result->countryCode = 'Se';
		$result->vatNumber = 'riouslyInvalidNumber';
		$result->requestIdentifier = null;

		$this->vatCheck->expects($this->any())
			->method('checkVatApprox')
			->with([
				'countryCode' => 'Se',
				'vatNumber' => 'riouslyInvalidNumber',
				'requesterCountryCode' => null,
				'requesterVatNumber' => null,
			])
			->willReturn($result);

		$vatNumber = 'SeriouslyInvalidNumber'; // but valid country SE
		$this->vatCalculator->setSoapClient($this->vatCheck);
		$result = $this->vatCalculator->isValidVatNumber($vatNumber);
		$this->assertFalse($result);
	}


	public function testValidateVatNumberThrowsExceptionOnSoapFailure(): void
	{
		$this->expectException(VatCheckUnavailableException::class);
		$this->vatCheck->expects($this->any())
			->method('checkVatApprox')
			->with([
				'countryCode' => 'Se',
				'vatNumber' => 'riouslyInvalidNumber',
				'requesterCountryCode' => null,
				'requesterVatNumber' => null,
			])
			->willThrowException(new SoapFault('Server', 'Something went wrong'));

		$vatNumber = 'SeriouslyInvalidNumber'; // but valid country SE
		$this->vatCalculator->setSoapClient($this->vatCheck);
		$this->vatCalculator->isValidVatNumber($vatNumber);
	}


	public function testValidateVatNumberThrowsExceptionOnInvalidCountry(): void
	{
		$this->expectException(UnsupportedCountryException::class);
		$this->expectExceptionMessage('Unsupported/non-EU country In');
		$this->vatCalculator->isValidVatNumber('InvalidEuCountry');
	}


	public function testValidateVatNumberThrowsExceptionOnInvalidChars(): void
	{
		$this->expectException(InvalidCharsInVatNumberException::class);
		$this->expectExceptionMessage('VAT number CY123Μ456_789 contains invalid characters: Μ (0xce9c) at offset 5, _ (0x5f) at offset 10');
		$this->vatCalculator->isValidVatNumber('CY123Μ456_789');
	}


	public function testCanValidateValidVatNumberWithRequesterVatNumber(): void
	{
		$result = new stdClass();
		$result->valid = true;
		$result->countryCode = 'DE';
		$result->vatNumber = '190098891';
		$result->requestIdentifier = 'FOOBAR338';

		$this->vatCheck->expects($this->any())
			->method('checkVatApprox')
			->with([
				'countryCode' => 'DE',
				'vatNumber' => '190098891',
				'requesterCountryCode' => 'CZ',
				'requesterVatNumber' => '26168685',
			])
			->willReturn($result);

		$vatNumber = 'DE 190 098 891';
		$this->vatCalculator->setBusinessVatNumber('CZ26168685');
		$this->vatCalculator->setSoapClient($this->vatCheck);
		$result = $this->vatCalculator->getVatDetails($vatNumber);
		$this->assertTrue($result->isValid());
		$this->assertSame('DE', $result->getCountryCode());
		$this->assertSame('190098891', $result->getVatNumber());
		$this->assertSame('FOOBAR338', $result->getRequestId());
	}


	public function testCanValidateValidVatNumberWithRequesterVatNumberSet(): void
	{
		$result = new stdClass();
		$result->valid = true;
		$result->countryCode = 'DE';
		$result->vatNumber = '190098891';
		$result->requestIdentifier = 'FOOBAR338';

		$this->vatCheck->expects($this->any())
			->method('checkVatApprox')
			->with([
				'countryCode' => 'DE',
				'vatNumber' => '190098891',
				'requesterCountryCode' => 'CZ',
				'requesterVatNumber' => '00006947',
			])
			->willReturn($result);

		$vatNumber = 'DE 190 098 891';
		$this->vatCalculator->setSoapClient($this->vatCheck);
		$result = $this->vatCalculator->getVatDetails($vatNumber, 'CZ00006947');
		$this->assertTrue($result->isValid());
		$this->assertSame('DE', $result->getCountryCode());
		$this->assertSame('190098891', $result->getVatNumber());
		$this->assertSame('FOOBAR338', $result->getRequestId());
	}


	public function testAddNonEuRateShouldCollectValidateThrows(): void
	{
		$this->assertFalse($this->vatCalculator->shouldCollectVat('NO'));
		$this->assertFalse($this->vatCalculator->shouldCollectEuVat('NO'));
		$this->vatRates->addRateForCountry('NO');
		$this->assertTrue($this->vatCalculator->shouldCollectVat('NO'));
		$this->assertFalse($this->vatCalculator->shouldCollectEuVat('NO'));

		$this->expectException(UnsupportedCountryException::class);
		$this->expectExceptionMessage('Unsupported/non-EU country No');

		$vatNumber = 'Norway132'; // unsupported country NO
		$result = $this->vatCalculator->isValidVatNumber($vatNumber);
		$this->assertFalse($result);
	}


	public function testAddGbRateShouldCollectWithPostalCodeException(): void
	{
		$this->assertFalse($this->vatCalculator->shouldCollectVat('GB'));
		$this->assertFalse($this->vatCalculator->shouldCollectEuVat('GB'));
		$result = $this->vatCalculator->calculate(24.00, 'GB', null, true);
		$this->assertEquals(24.00, $result->getPrice());
		$this->assertEquals(0.00, $result->getTaxRate());
		$this->assertEquals(0.00, $result->getTaxValue());

		$this->vatRates->addRateForCountry('GB');
		$this->assertTrue($this->vatCalculator->shouldCollectVat('GB'));
		$this->assertFalse($this->vatCalculator->shouldCollectEuVat('GB'));

		// Valid UK post code
		$postalCode = 'S1A 2AA';
		$result = $this->vatCalculator->calculate(24.00, 'GB', $postalCode, false);
		//Expect standard rate for UK
		$this->assertEquals(28.80, round($result->getPrice(), 2));
		$this->assertEquals(0.20, $result->getTaxRate());
		$this->assertEquals(4.80, round($result->getTaxValue(), 2));

		$postalCode = 'BFPO58'; // Dhekelia
		$result = $this->vatCalculator->calculate(24.00, 'GB', $postalCode, false);
		$this->assertEquals(28.56, round($result->getPrice(), 2));
		$this->assertEquals(0.19, $result->getTaxRate());
		$this->assertEquals(4.56, round($result->getTaxValue(), 2));
	}


	public function testSetBusinessCountryCode(): void
	{
		$this->vatCalculator->setBusinessCountryCode('DE');
		$result = $this->vatCalculator->calculate(24.00, 'DE', null, true);
		$this->assertEquals(28.56, round($result->getPrice(), 2));
		$this->assertEquals(0.19, $result->getTaxRate());
		$this->assertEquals(4.56, round($result->getTaxValue(), 2));

		$this->vatCalculator->setBusinessCountryCode('NL');
		$result = $this->vatCalculator->calculate(24.00, 'DE', null, true);
		$this->assertEquals(24.00, $result->getPrice());
		$this->assertEquals(0.00, $result->getTaxRate());
		$this->assertEquals(0.00, $result->getTaxValue());
	}


	public function testChecksPostalCodeForVatExceptions(): void
	{
		$postalCode = '27498'; // Heligoland
		$result = $this->vatCalculator->calculate(24.00, 'DE', $postalCode, false);
		$this->assertEquals(24.00, $result->getPrice());
		$this->assertEquals(0.00, $result->getTaxRate());
		$this->assertEquals(0.00, $result->getTaxValue());

		$postalCode = '6691'; // Jungholz
		$result = $this->vatCalculator->calculate(24.00, 'AT', $postalCode, false);
		$this->assertEquals(28.56, round($result->getPrice(), 2));
		$this->assertEquals(0.19, $result->getTaxRate());
		$this->assertEquals(4.56, round($result->getTaxValue(), 2));

		$postalCode = '9122'; // Madeira
		$result = $this->vatCalculator->calculate(24.00, 'PT', $postalCode, false);
		$this->assertEquals(29.28, round($result->getPrice(), 2));
		$this->assertEquals(0.22, $result->getTaxRate());
		$this->assertEquals(5.28, round($result->getTaxValue(), 2));
	}


	public function testPostalCodesWithoutExceptionsGetStandardRate(): void
	{
		// Invalid post code
		$postalCode = 'IGHJ987ERT35';
		$result = $this->vatCalculator->calculate(24.00, 'ES', $postalCode, false);
		//Expect standard rate for Spain
		$this->assertEquals(29.04, round($result->getPrice(), 2));
		$this->assertEquals(0.21, $result->getTaxRate());
		$this->assertEquals(5.04, round($result->getTaxValue(), 2));
	}


	public function testShouldCollectVat(): void
	{
		$this->assertTrue($this->vatCalculator->shouldCollectVat('DE'));
		$this->assertTrue($this->vatCalculator->shouldCollectVat('NL'));
		$this->assertFalse($this->vatCalculator->shouldCollectVat(''));
		$this->assertFalse($this->vatCalculator->shouldCollectVat('XXX'));
	}


	public function testCalculateNet(): void
	{
		$result = $this->vatCalculator->calculateNet(28.56, 'DE', null, false);
		$this->assertEquals(24.00, round($result->getNetPrice(), 2));
		$this->assertEquals(0.19, $result->getTaxRate());
		$this->assertEquals(4.56, round($result->getTaxValue(), 2));

		$result = $this->vatCalculator->calculateNet(28.56, 'DE', null, true);
		$this->assertEquals(28.56, $result->getPrice());
		$this->assertEquals(0, $result->getTaxRate());
		$this->assertEquals(0, $result->getTaxValue());
	}


	public function testCalculateHighVatType(): void
	{
		$result = $this->vatCalculator->calculate(24.00, 'NL', null, false, VatRates::STANDARD_RATE);
		$this->assertEquals(29.04, round($result->getPrice(), 2));
	}


	public function testCalculateLowVatType(): void
	{
		$result = $this->vatCalculator->calculate(24.00, 'NL', null, false, VatRates::REDUCED_RATE);
		$this->assertEquals(26.16, round($result->getPrice(), 2));
	}

}
