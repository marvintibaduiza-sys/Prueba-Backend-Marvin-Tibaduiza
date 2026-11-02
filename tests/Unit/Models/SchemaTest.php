<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The domain tables follow the data model given in the assessment, plus Eloquent timestamps.
 */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public static function assessmentDataModel(): array
    {
        return [
            'products' => ['products', ['id', 'name', 'description', 'price', 'currency_id', 'tax_cost', 'manufacturing_cost']],
            'currencies' => ['currencies', ['id', 'name', 'symbol', 'exchange_rate']],
            'product prices' => ['product_prices', ['id', 'product_id', 'currency_id', 'price']],
        ];
    }

    #[DataProvider('assessmentDataModel')]
    public function test_table_has_exactly_the_columns_of_the_assessment_data_model(string $table, array $columns): void
    {
        $this->assertEqualsCanonicalizing(
            [...$columns, 'created_at', 'updated_at'],
            Schema::getColumnListing($table)
        );
    }
}
