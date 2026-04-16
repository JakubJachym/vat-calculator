<?php
declare(strict_types = 1);

namespace JakubJachym\VatCalculator\Exceptions;

class InvalidCharsInVatNumberException extends VatNumberException
{

	/** @var array<int, string> */
	private array $invalidChars = [];


	/**
	 * @param array<int, array{0: string, 1: int}> $invalidChars
	 * @param string $vatNumber
	 */
	public function __construct(array $invalidChars, string $vatNumber)
	{
		$chars = [];
		foreach ($invalidChars as $invalidChar) {
			$chars[] = sprintf('%s (0x%s) at offset %d', $invalidChar[0], bin2hex($invalidChar[0]), $invalidChar[1]);
			$this->invalidChars[$invalidChar[1]] = $invalidChar[0];
		}
		parent::__construct("VAT number {$vatNumber} contains invalid characters: " . implode(', ', $chars));
	}


	/**
	 * @return array<int, string> byte offset => character
	 */
	public function getInvalidChars(): array
	{
		return $this->invalidChars;
	}

}
