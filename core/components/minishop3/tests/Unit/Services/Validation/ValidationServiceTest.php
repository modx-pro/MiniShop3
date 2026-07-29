<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Validation;

use MiniShop3\Services\Validation\ValidationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidationServiceTest extends TestCase
{
    private ValidationService $validator;

    protected function setUp(): void
    {
        $this->validator = new ValidationService();
    }

    public function testRequiredAndMinRulesMatchCustomerDefaults(): void
    {
        $messages = [
            'required' => 'Required',
            'email' => 'Invalid email',
            'min' => 'Minimum :min characters',
        ];

        $fail = $this->validator->validate(
            ['first_name' => 'a'],
            ['first_name' => 'required|min:2'],
            $messages
        );

        self::assertTrue($fail->fails());
        self::assertSame('Minimum 2 characters', $fail->errors()->first('first_name'));

        $pass = $this->validator->validate(
            ['first_name' => 'Ann'],
            ['first_name' => 'required|min:2'],
            $messages
        );

        self::assertTrue($pass->passes());
    }

    public function testEmailRule(): void
    {
        $messages = ['email' => 'Invalid email'];

        $fail = $this->validator->validate(
            ['email' => 'not-an-email'],
            ['email' => 'required|email'],
            $messages
        );

        self::assertSame('Invalid email', $fail->errors()->first('email'));

        $pass = $this->validator->validate(
            ['email' => 'user@example.com'],
            ['email' => 'required|email'],
            $messages
        );

        self::assertTrue($pass->passes());
    }

    public function testOrderFieldNumericRules(): void
    {
        $messages = [
            'required' => 'Required',
            'numeric' => 'Must be a number',
        ];

        $fail = $this->validator->validate(
            ['delivery_id' => 'abc'],
            ['delivery_id' => 'required|numeric'],
            $messages
        );

        self::assertTrue($fail->fails());
        self::assertSame('Must be a number', $fail->errors()->first('delivery_id'));

        $pass = $this->validator->validate(
            ['delivery_id' => '12'],
            ['delivery_id' => 'required|numeric'],
            $messages
        );

        self::assertTrue($pass->passes());
    }

    public function testProfileFieldRules(): void
    {
        $rules = [
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'phone' => 'required|min:10|max:20',
        ];

        $data = [
            'first_name' => 'Jo',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ];

        self::assertTrue($this->validator->validate($data, $rules)->passes());

        $shortPhone = $data;
        $shortPhone['phone'] = '123';

        self::assertTrue($this->validator->validate($shortPhone, $rules)->fails());
    }

    public function testNullableSkipsOtherRulesWhenEmpty(): void
    {
        $result = $this->validator->validate(
            ['nickname' => ''],
            ['nickname' => 'nullable|email']
        );

        self::assertTrue($result->passes());
    }

    public function testFirstOfAllReturnsFieldKeyedErrors(): void
    {
        $result = $this->validator->validate(
            ['first_name' => '', 'email' => 'bad'],
            [
                'first_name' => 'required|min:2',
                'email' => 'required|email',
            ],
            [
                'required' => 'Required',
                'email' => 'Invalid email',
            ]
        );

        self::assertSame(
            [
                'first_name' => 'Required',
                'email' => 'Invalid email',
            ],
            $result->errors()->firstOfAll()
        );
    }

    public function testMakeAllowsDeferredValidation(): void
    {
        $result = $this->validator->make(
            ['phone' => '12345'],
            ['phone' => 'required|min:10']
        );

        self::assertSame(0, $result->errors()->count());

        $result->validate();

        self::assertTrue($result->fails());
    }

    public function testUnsupportedRuleFailsValidation(): void
    {
        $result = $this->validator->validate(
            ['custom' => 'value'],
            ['custom' => 'unknown_rule_name']
        );

        self::assertTrue($result->fails());
    }

    #[DataProvider('deliverySeedRulesProvider')]
    public function testDeliverySeedRules(string $field, mixed $value, bool $shouldPass): void
    {
        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
        ];

        $result = $this->validator->validate([$field => $value], [$field => $rules[$field]]);

        self::assertSame($shouldPass, $result->passes(), "field {$field}");
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed, 2: bool}>
     */
    public static function deliverySeedRulesProvider(): iterable
    {
        yield 'first_name ok' => ['first_name', 'Ivan', true];
        yield 'first_name empty' => ['first_name', '', false];
        yield 'email ok' => ['email', 'a@b.co', true];
        yield 'email invalid' => ['email', 'nope', false];
    }

    public function testDigitsRuleRequiresNumericOnlyString(): void
    {
        $result = $this->validator->validate(
            ['phone' => '12-345-67890'],
            ['phone' => 'digits:10']
        );

        self::assertTrue($result->fails());

        $valid = $this->validator->validate(
            ['phone' => '1234567890'],
            ['phone' => 'digits:10']
        );

        self::assertTrue($valid->passes());
    }

    public function testDigitsBetweenRuleAcceptsLengthInRange(): void
    {
        $tooShort = $this->validator->validate(
            ['code' => '12'],
            ['code' => 'digits_between:3,5']
        );

        self::assertTrue($tooShort->fails());

        $lowerBound = $this->validator->validate(
            ['code' => '123'],
            ['code' => 'digits_between:3,5']
        );

        self::assertTrue($lowerBound->passes());

        $upperBound = $this->validator->validate(
            ['code' => '12345'],
            ['code' => 'digits_between:3,5']
        );

        self::assertTrue($upperBound->passes());

        $tooLong = $this->validator->validate(
            ['code' => '123456'],
            ['code' => 'digits_between:3,5']
        );

        self::assertTrue($tooLong->fails());
    }

    public function testDigitsBetweenRuleRejectsNonDigitCharacters(): void
    {
        $result = $this->validator->validate(
            ['code' => '12a45'],
            ['code' => 'digits_between:3,5']
        );

        self::assertTrue($result->fails());
    }

    public function testDigitsBetweenRuleFailsWithMissingParams(): void
    {
        $result = $this->validator->validate(
            ['code' => '123'],
            ['code' => 'digits_between:3']
        );

        self::assertTrue($result->fails());
    }

    public function testRegexRuleKeepsCommaInsidePattern(): void
    {
        $result = $this->validator->validate(
            ['code' => 'foo,bar'],
            ['code' => 'regex:/^[a-z,]+$/']
        );

        self::assertTrue($result->passes());
    }
}
