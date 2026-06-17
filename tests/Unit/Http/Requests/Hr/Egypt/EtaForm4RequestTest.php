<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Hr\Egypt;

use App\Http\Requests\Hr\Egypt\EtaForm4Request;
use PHPUnit\Framework\TestCase;

final class EtaForm4RequestTest extends TestCase
{
    private EtaForm4Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new EtaForm4Request();
    }

    // -----------------------------------------------------------------------
    // rules()
    // -----------------------------------------------------------------------

    /** @test */
    public function rules_requires_year(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('required', $rules['year']);
    }

    /** @test */
    public function rules_requires_quarter(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('required', $rules['quarter']);
    }

    /** @test */
    public function rules_requires_year_to_be_integer(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('integer', $rules['year']);
    }

    /** @test */
    public function rules_requires_quarter_to_be_integer(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('integer', $rules['quarter']);
    }

    /** @test */
    public function rules_sets_year_minimum_to_2022(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('min:2022', $rules['year']);
    }

    /** @test */
    public function rules_sets_year_maximum_to_2030(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('max:2030', $rules['year']);
    }

    /** @test */
    public function rules_sets_quarter_minimum_to_1(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('min:1', $rules['quarter']);
    }

    /** @test */
    public function rules_sets_quarter_maximum_to_4(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('max:4', $rules['quarter']);
    }

    // -----------------------------------------------------------------------
    // messages()
    // -----------------------------------------------------------------------

    /** @test */
    public function messages_covers_all_rule_keys(): void
    {
        $messages = $this->request->messages();
        $expected = ['year.required', 'year.integer', 'year.min', 'year.max',
                     'quarter.required', 'quarter.integer', 'quarter.min', 'quarter.max'];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $messages, "Missing message for [{$key}]");
        }
    }

    /** @test */
    public function messages_are_non_empty_strings(): void
    {
        foreach ($this->request->messages() as $key => $message) {
            $this->assertNotEmpty($message, "Message for [{$key}] must not be empty");
        }
    }

    // -----------------------------------------------------------------------
    // authorize()
    // -----------------------------------------------------------------------

    /** @test */
    public function authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }
}
