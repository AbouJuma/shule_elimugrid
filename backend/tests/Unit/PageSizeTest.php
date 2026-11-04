<?php

namespace Tests\Unit;

use App\Support\Pagination\PageSize;
use Illuminate\Http\Request;
use Tests\TestCase;

class PageSizeTest extends TestCase
{
    public function test_resolves_a_valid_requested_size(): void
    {
        $request = Request::create('/students', 'GET', ['per_page' => 100]);

        $this->assertSame(100, PageSize::resolve($request));
    }

    public function test_falls_back_to_default_when_missing(): void
    {
        $request = Request::create('/students', 'GET');

        $this->assertSame(20, PageSize::resolve($request));
        $this->assertSame(15, PageSize::resolve($request, 15));
    }

    /**
     * Not just "an unrecognized value is rejected" — this is the exact
     * scenario that caused a real bug in the page-size <select> (it
     * showed no option selected at all when per_page was invalid,
     * because the view compared against the raw request value instead
     * of calling PageSize::resolve() itself). Covered here so that
     * regression can't silently come back.
     */
    public function test_an_arbitrarily_large_value_does_not_bypass_the_allowed_list(): void
    {
        $request = Request::create('/students', 'GET', ['per_page' => 999999]);

        $this->assertSame(20, PageSize::resolve($request));
    }

    public function test_a_non_numeric_value_falls_back_to_default(): void
    {
        $request = Request::create('/students', 'GET', ['per_page' => 'all']);

        $this->assertSame(20, PageSize::resolve($request));
    }
}
