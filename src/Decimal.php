<?php
namespace Spryker\Decimal;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;

class Decimal implements JsonSerializable
{
    public const MAX_SCALE = 2147483647; // 2^32-1

    public const EXP_MARK = 'e';
    public const RADIX_MARK = '.';

    public const ROUND_TRUNCATE = 0;
    public const ROUND_UP = PHP_ROUND_HALF_UP;
    public const ROUND_DOWN = PHP_ROUND_HALF_DOWN;
    public const ROUND_CEILING = 3;
    public const ROUND_FLOOR = 4;

    /**
     * Integral part of this decimal number.
     *
     * Value before the separator. Cannot be negative.
     *
     * @var int
     */
    protected $integralPart;

    /**
     * Fractional part of this decimal number.
     *
     * Value after the separator (decimals) as string. Must be numbers only.
     *
     * @var string
     */
    protected $fractionalPart;

    /**
     * @var bool
     */
    protected $negative;

    /**
     * decimal(10,6) => 10
     *
     * @var int
     */
    protected $scale;

    /**
     * decimal(10,6) => 6
     *
     * @var int
     */
    protected $precision;

    /**
     * @param string|int|float|static $value
     * @param int|null $scale
     */
    public function __construct($value, ?int $scale = null)
    {
        if (!is_string($value)) {
            $value = (string)$value;
        }

        $value = $this->normalizeValue($value);

        $this->setValue($value, $scale);
        $this->setScale($scale);
        //$this->setPrecision() // not needed?
    }

    /**
     * @return int
     */
    public function scale(): int
    {
        return $this->scale;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function normalizeValue(string $value): string
    {
        if (strpos($value, '-.') === 0) {
            $value = '-0.' . substr($value, 2);
        }

        return trim($value);
    }

    /**
     * @param int|null $integerPart
     * @param string|null $decimalPart
     * @param bool|null $negative
     *
     * @return static
     */
    protected function copy(?int $integerPart = null, ?string $decimalPart = null, ?bool $negative = null)
    {
        $clone = clone $this;
        if ($integerPart !== null) {
            $clone->integralPart = $integerPart;
        }
        if ($decimalPart !== null) {
            $clone->fractionalPart = $decimalPart;
        }
        if ($negative !== null) {
            $clone->negative = $negative;
        }

        return $clone;
    }

    /**
     * Returns a Decimal instance from the given value.
     *
     * If the value is already a Decimal instance, then (since immutable) return it unmodified.
     * Otherwise, create a new Decimal instance from the given value and return
     * it.
     *
     * @param string|int|float|static $value
     * @param int|null $scale
     *
     * @return static
     */
    public static function create($value, ?int $scale = null)
    {
        if ($scale === null && $value instanceof static) {
            return clone $value;
        }

        return new static($value, $scale);
    }

    /**
     * Equality
     *
     * This method is equivalent to the `==` operator.
     *
     * @param string|int|float|static $value
     *
     * @return bool TRUE if this decimal is considered equal to the given value.
     *  Equal decimal values tie-break on precision.
     */
    public function equals($value): bool
    {
        return $this->compareTo($value) === 0;
    }

    /**
     * @param string|int|float|static $value
     *
     * @return bool
     */
    public function greaterThan($value): bool
    {
        return $this->compareTo($value) > 0;
    }

    /**
     * @param string|int|float|static $value
     *
     * @return bool
     */
    public function lessThan($value): bool
    {
        return $this->compareTo($value) < 0;
    }

    /**
     * @param string|int|float|static $value
     *
     * @return bool
     */
    public function greatherThanOrEquals($value): bool
    {
        return ($this->compareTo($value) >= 0);
    }

    /**
     * @param string|int|float|static $value
     *
     * @return bool
     */
    public function lessThanOrEquals($value): bool
    {
        return ($this->compareTo($value) <= 0);
    }

    /**
     * Compare this Decimal with a value.
     *
     * Returns
     * - `-1` if the instance is less than the $value,
     * - `0` if the instance is equal to $value, or
     * - `1` if the instance is greater than $value.
     *
     * @param string|int|float|static $value
     *
     * @return int
     */
    public function compareTo($value): int
    {
        $decimal = static::create($value);
        $scale = max($this->scale(), $decimal->scale());

        return bccomp($this, $decimal, $scale);
    }

    /**
     * Add $value to this Decimal and return the sum as a new Decimal.
     *
     * @param string|int|float|static $value
     * @param int|null $scale
     *
     * @return static
     */
    public function add($value, ?int $scale = null)
    {
        $decimal = static::create($value);
        $scale = static::resultScale($this, $decimal, $scale);

        return new static(bcadd($this, $decimal, $scale));
    }

    /**
     * Return an appropriate scale for an arithmetic operation on two Decimals.
     *
     * If $scale is specified and is a valid positive integer, return it.
     * Otherwise, return the higher of the scales of the operands.
     *
     * @param static $a
     * @param static $b
     * @param int|null $scale
     *
     * @return int
     */
    protected static function resultScale($a, $b, ?int $scale = null): int
    {
        if ($scale === null) {
            $scale = max($a->scale(), $b->scale());
        }

        return $scale;
    }

    /**
     * Subtract $value from this Decimal and return the difference as a new
     * Decimal.
     *
     * @param string|int|float|static $value
     * @param int|null $scale
     *
     * @return static
     */
    public function subtract($value, ?int $scale = null)
    {
        $decimal = static::create($value);
        $scale = static::resultScale($this, $decimal, $scale);

        return new static(bcsub($this, $decimal, $scale));
    }

    /**
     * Trims trailing zeroes.
     *
     * @return static
     */
    public function trim()
    {
        return $this->copy($this->integralPart, $this->trimDecimals($this->fractionalPart));
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function trimDecimals(string $value): string
    {
        return rtrim($value, '0') ?: '';
    }

    /**
     * Signum
     *
     * @return int 0 if zero, -1 if negative, or 1 if positive.
     */
    public function sign(): int
    {
        if ($this->isZero()) {
            return 0;
        }

        return $this->negative ? -1 : 1;
    }

    /**
     * Returns the absolute (positive) value of this decimal.
     *
     * @return static
     */
    public function absolute()
    {
        return $this->copy($this->integralPart, $this->fractionalPart, false);
    }

    /**
     * Returns the negation.
     *
     * @return static
     */
    public function negation()
    {
        return $this->copy(null, null, !$this->isNegative());
    }

    /**
     * @return bool
     */
    public function isZero(): bool
    {
        return $this->integralPart === 0 && trim($this->fractionalPart, '0') === '';
    }

    /**
     * @return bool
     */
    public function isNegative(): bool
    {
        return $this->negative;
    }

    /**
     * @return bool
     */
    public function isPositive(): bool
    {
        return !$this->negative && !$this->isZero();
    }

    /**
     * Multiply this Decimal by $value and return the product as a new Decimal.
     *
     * @param string|int|float|static $value
     * @param int|null $scale
     *
     * @return static
     */
    public function multiply($value, ?int $scale = null)
    {
        $decimal = static::create($value);
        if ($scale === null) {
            $scale = $this->scale() + $decimal->scale();
        }

        return new static(bcmul($this, $decimal, $scale));
    }

    /**
     * Divide this Decimal by $value and return the quotient as a new Decimal.
     *
     * @param string|int|float|static $value
     * @param int|null $scale
     *
     * @throws \LogicException if $value is zero.
     *
     * @return static
     */
    public function divide($value, ?int $scale = null)
    {
        $decimal = static::create($value);
        if ($decimal->isZero()) {
            throw new LogicException('Cannot divide by zero. Only Chuck Norris can!');
        }

        $scale = static::resultScale($this, $decimal, $scale);

        return new static(bcdiv($this, $decimal, $scale));
    }

    /**
     * Return some approximation of this Decimal as a PHP native float.
     *
     * Due to the nature of binary floating-point, some valid values of Decimal
     * will not have any finite representation as a float, and some valid
     * values of Decimal will be out of the range handled by floats.
     *
     * @return float
     */
    public function toFloat(): float
    {
        return (float)$this->toString();
    }

    /**
     * Returns the decimal as int. Does not round.
     *
     * This method is equivalent to a cast to int.
     *
     * @return int
     */
    public function toInt(): int
    {
        return (int)$this->toString();
    }

    /**
     * String representation.
     *
     * This method is equivalent to a cast to string.
     *
     * This method should not be used as a canonical representation of this
     * decimal, because values can be represented in more than one way. However,
     * this method does guarantee that a decimal instantiated by its output with
     * the same precision will be exactly equal to this decimal.
     *
     * @return string the value of this decimal represented exactly, in either
     *                fixed or scientific form, depending on the value.
     */
    public function toString(): string
    {
        $decimalPart = $this->fractionalPart !== '' ? '.' . $this->fractionalPart : '';

        return ($this->negative ? '-' : '') . $this->integralPart . $decimalPart;
    }

    /**
     * Return a basic string representation of this Decimal.
     *
     * The output of this method is guaranteed to yield exactly the same value
     * if fed back into the Decimal constructor.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Get the printable version of this object
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->toString(),
            'scale' => $this->scale,
        ];
    }

    /**
     * Separates int and decimal parts and adds them to the state.
     *
     * - Removes leading 0 on int part
     * - '0.00001' can also come in as '1.0E-5'
     *
     * @param string $value
     * @param int|null $scale
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function setValue(string $value, ?int $scale): void
    {
        preg_match('#(.+)e(.+)#i', $value, $matches);
        if (!$matches) {
            $separatorPos = strpos($value, '.');
            if ($separatorPos !== false) {
                $before = (int)substr($value, 0, $separatorPos);
                $after = substr($value, $separatorPos + 1);
            } else {
                $before = (int)$value;
                $after = '';
            }

            $this->negative = $before < 0 || $before === 0 && $after !== '' && strpos($value, '-') === 0;
            $this->integralPart = abs($before);
            $this->fractionalPart = $after;

            return;
        }

        $pattern = '/^(-?)(\d+(?:' . static::RADIX_MARK . '\d*)?|' .
            '[' . static::RADIX_MARK . ']' . '\d+)' . static::EXP_MARK . '(-?\d*)?$/i';
        preg_match($pattern, $value, $matches);
        if (!$matches) {
            throw new InvalidArgumentException('Invalid value/notation: ' . $value);
        }

        $negativeChar = $matches[1];
        $value = (float)$matches[2];
        $exp = (int)$matches[3];

        if ($exp < 0) {
            $this->integralPart = 0;
            $this->fractionalPart = str_repeat('0', -$exp - 1) . str_replace('.', '', (string)$value);

            if ($scale !== null) {
                $this->fractionalPart = str_pad($this->fractionalPart, $scale, '0');
            }
        } else {
            $this->integralPart = abs((int)($value * pow(10, $exp)));
            $this->fractionalPart = '';

            if ($scale !== null) {
                $this->fractionalPart = str_pad($this->fractionalPart, $scale - strlen((string)$this->integralPart), '0');
            }
        }

        $this->negative = $negativeChar === '-';
    }

    /**
     * @param int|null $scale
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function setScale(?int $scale): void
    {
        $calculatedScale = strlen($this->fractionalPart);
        if ($scale && $calculatedScale > $scale) {
            throw new InvalidArgumentException('Loss of precision detected. Detected scale `' . $calculatedScale . '` > `' . $scale . '` as defined.');
        }

        $this->scale = $scale ?? $calculatedScale;
    }

    /**
     * @param int|null $scale
     *
     * @return void
     */
    protected function _setPrecision(?int $scale): void
    {
        if ($scale) {
            $this->precision = $scale;

            return;
        }

        if ($this->integralPart) {
            $scale = strlen((string)$this->integralPart) + strlen($this->fractionalPart);
        } else {
            $fractionalPart = ltrim($this->fractionalPart, '0');
            $scale = strlen($fractionalPart);
        }

        $this->precision = $scale;
    }
}
