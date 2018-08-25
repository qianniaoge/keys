<?php

namespace Tests\Unit\Controllers;

use App\Events\RandomPageGenerated;
use App\Keys\PageNumbers\BitcoinPageNumber;
use App\Models\BiggestRandomPage;
use App\Models\CoinStats;
use App\Models\SmallestRandomPage;
use App\Support\Enums\CoinType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function it_can_show_a_page()
    {
        $this->getPage('8457934857349')->assertStatus(200);
    }

    /** @test */
    function it_can_show_a_random_page()
    {
        $this->expectsEvents(RandomPageGenerated::class);

        $this->assertSame(0, CoinStats::today(CoinType::BITCOIN)->random_pages_generated);

        $this->followingRedirects()
            ->getRandomPage()
            ->assertStatus(200)
            ->assertViewIs('bitcoin-page');

        $this->assertSame(1, CoinStats::today(CoinType::BITCOIN)->random_pages_generated);
    }

    /** @test */
    function it_keeps_track_of_page_views_stats()
    {
        $this->assertSame(0, CoinStats::today(CoinType::BITCOIN)->pages_viewed);
        $this->assertSame(0, CoinStats::today(CoinType::BITCOIN)->keys_generated);

        $this->getPage('1')->assertStatus(200);

        $this->assertSame(1, CoinStats::today(CoinType::BITCOIN)->pages_viewed);
        $this->assertSame(128, CoinStats::today(CoinType::BITCOIN)->keys_generated);

        $this->getPage(BitcoinPageNumber::lastPageNumber())->assertStatus(200);

        $this->assertSame(2, CoinStats::today(CoinType::BITCOIN)->pages_viewed);
        $this->assertSame(192, CoinStats::today(CoinType::BITCOIN)->keys_generated);
    }

    /** @test */
    function biggest_and_smallest_random_bitcoin_page_get_stored()
    {
        $redirectUrl = $this->getRandomPage()
            ->assertStatus(302)
            ->headers
            ->get('location');

        $randomNumber = last(explode('/', $redirectUrl));

        $this->assertTrue(strlen($randomNumber) > 10);

        $this->assertSame(1, SmallestRandomPage::count());
        $this->assertSame($randomNumber, SmallestRandomPage::smallest(CoinType::BITCOIN));

        $this->assertSame(1, BiggestRandomPage::count());
        $this->assertSame($randomNumber, BiggestRandomPage::biggest(CoinType::BITCOIN));
    }

    /** @test */
    function it_stores_the_new_smallest_number()
    {
        SmallestRandomPage::create([
            'coin' => CoinType::BITCOIN,
            'page_number' => '519480938980827735392876',
        ]);

        RandomPageGenerated::dispatch(
            new BitcoinPageNumber('519480938980827735392877')
        );

        $this->assertSame(1, SmallestRandomPage::count());

        RandomPageGenerated::dispatch(
            new BitcoinPageNumber('99948093898')
        );

        $this->assertSame(2, SmallestRandomPage::count());

        $this->assertSame('99948093898', SmallestRandomPage::smallest(CoinType::BITCOIN));
    }

    /** @test */
    function it_stores_the_new_biggest_number()
    {
        SmallestRandomPage::create([
            'coin' => CoinType::BITCOIN,
            'page_number' => '519480938980827735392876',
        ]);

        RandomPageGenerated::dispatch(
            new BitcoinPageNumber('519480938980827735392875')
        );

        $this->assertSame(1, BiggestRandomPage::count());

        RandomPageGenerated::dispatch(
            new BitcoinPageNumber('519480938980827735392877')
        );

        $this->assertSame(2, BiggestRandomPage::count());

        $this->assertSame('519480938980827735392877', BiggestRandomPage::biggest(CoinType::BITCOIN));
    }

    private function getPage($number)
    {
        return $this->get(route('btcPages', $number));
    }

    private function getRandomPage()
    {
        return $this->get(route('btcPages.random'));
    }
}
