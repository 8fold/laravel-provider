<?php

use Orchestra\Testbench\BrowserKit\TestCase;
// use Orchestra\Testbench\TestCase;

use Eightfold\Site\Tests\TestContentBuilder as ContentBuilder;

use Eightfold\ShoopExtras\Shoop;

class ContentBuilderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Eightfold\Site\Tests\TestProvider'];
    }

    public function testCanGetRemoteAssets()
    {
        $expected = 5;
        $actual = ContentBuilder::meta()->count;
        $this->assertEquals($expected, $actual);
    }

    public function testUri()
    {
        $this->visit("/somewhere");
        $expected = "/somewhere";
        $actual = ContentBuilder::uri();
        $this->assertSame($expected, $actual->unfold());

        $this->visit("/somewhere/else");

        $expected = ["somewhere", "else"];
        $actual = ContentBuilder::uriParts();
        $this->assertSame($expected, $actual->unfold());

        $expected = "somewhere";
        $actual = ContentBuilder::uriRoot();
        $this->assertSame($expected, $actual->unfold());

        $this->visit("/");
        $expected = "";
        $actual = ContentBuilder::uriRoot();
        $this->assertSame($expected, $actual->unfold());
    }

    public function testStore()
    {
        $expected = __DIR__ ."/content";
        $actual = ContentBuilder::contentStore();
        $this->assertSame($expected, $actual->unfold());

        $expected = "Root";
        $actual = ContentBuilder::uriContentStore()->markdown()->meta()->title;
        $this->assertSame($expected, $actual);

        $this->visit("/somewhere/else");
        $expected = "Else";
        $actual = ContentBuilder::uriContentStore()->markdown()->meta()->title;
        $this->assertSame($expected, $actual);
    }

    public function testPageTitle()
    {
        $this->visit("/somewhere/else");
        $base = __DIR__;
        $expected = "Else | Somewhere | Root";
        $actual = ContentBuilder::uriPageTitle();
        $this->assertSame($expected, $actual->unfold());
    }

    public function testPageContent()
    {
        $this->visit("/somewhere/else")->see("Hello, World!");
    }

    public function testRss()
    {
        $expected = __DIR__ ."/content/feed/content.md";
        $actual = ContentBuilder::rssItemsStore();
        $this->assertEquals($expected, $actual->unfold());

        $expected = [
            "/somewhere/else",
            "/somewhere",
            "/"
        ];
        $actual = ContentBuilder::rssItemsStoreItems();
        $this->assertEquals($expected, $actual->unfold());

        $expected = "Copyright © Eightfold ". date("Y") .". All rights reserved.";
        $actual = ContentBuilder::copyright();
        $this->assertEquals($expected, $actual);

        $expected = '<?xml version="1.0"?>'."\n".'<rss version="2.0"><channel><title>8fold Laravel Service Provider</title><link>https://8fold.dev</link><description>A generic service provider for most 8fold projects.</description><language>en-us</language><copyright>Copyright © Eightfold 2020. All rights reserved.</copyright><item><title>Else</title><link>https://8fold.dev/somewhere/else</link><guid>https://8fold.dev/somewhere/else</guid><description>Hello, World!</description><pubDate>Wed, 01 Apr 2020 12:00:00 -0400</pubDate></item></channel></rss>';
        $actual = ContentBuilder::rssCompiled();
        $this->assertEquals($expected, $actual->unfold());
    }
}
