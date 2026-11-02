<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ArchitectureTest extends TestCase
{
    /**
     * The assessment requires Eloquent for every database interaction: application code must
     * not query tables through the query builder (DB::table / raw statements).
     */
    public function test_application_code_uses_eloquent_instead_of_the_query_builder(): void
    {
        $offenders = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match('/DB::(table|select|insert|update|delete|statement|raw)\(/', file_get_contents($file->getPathname()))) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame([], $offenders, 'Query builder used instead of Eloquent in: '.implode(', ', $offenders));
    }
}
