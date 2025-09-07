# Partial Key Verification Library for PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kduma/pkv.svg?style=flat-square)](https://packagist.org/packages/kduma/pkv)
[![Tests](https://img.shields.io/github/actions/workflow/status/kduma-OSS/PHP-Partial-Key-Verification/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/kduma-OSS/PHP-Partial-Key-Verification/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/kduma/pkv.svg?style=flat-square)](https://packagist.org/packages/kduma/pkv)

This is a port of my other C# library [Partial Key Verification Library for Compact Framework](https://opensource.duma.sh/libraries/net/partial-key-verification) into a PHP package.

This library implements Partial Key Verification (PKV). PKV is a cryptographic technique that allows verification of a subset of a key without revealing the entire key, enhancing security and privacy in various applications.

Check full documentation here: [opensource.duma.sh/libraries/php/partial-key-verification](https://opensource.duma.sh/libraries/php/partial-key-verification)


## Installation

You can install the package via composer:

```bash
composer require kduma/pkv
```

## Usage

```php
private static function validateKey(string $key): bool {
	$seed = \KDuma\PKV\PartialKeyValidator::getSerialNumberFromKey($key);
	$blacklist = [1518008798, 42];
	if (in_array($seed, $blacklist, true))
		return false;

	// Validation for key with index 1
	if (!\KDuma\PKV\PartialKeyValidator::validateKey(new \KDuma\PKV\Checksum\Adler16(), new \KDuma\PKV\Hash\OneAtATime(), $key, 1, 766109221))
		return false;

	// Validation for key with index 4
	if (!\KDuma\PKV\PartialKeyValidator::validateKey(new \KDuma\PKV\Checksum\Adler16(), new \KDuma\PKV\Hash\Fnv1a(), $key, 4, 4072442218))
		return false;

	return true;
}
```

## Testing

```bash
composer test
```

## Credits

- [Krystian Duma](https://github.com/kduma)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Packagist
View this package on Packagist.org: [kduma/pkv](https://packagist.org/packages/kduma/pkv)
