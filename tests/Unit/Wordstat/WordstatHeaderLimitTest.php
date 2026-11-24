<?php

namespace Tests\Unit\Wordstat;

use App\Support\ModuleHeaderLimitResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class WordstatHeaderLimitTest extends TestCase
{
    public function testWordstatPagesShowWordstatLimitInHeader(): void
    {
        $this->assertSame('WordstatKeywords', ModuleHeaderLimitResolver::resolve(Request::create('/wordstat-keywords')));
        $this->assertSame('WordstatKeywords', ModuleHeaderLimitResolver::resolve(Request::create('/wordstat-keywords/1')));
    }
}
