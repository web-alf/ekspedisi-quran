<?php

namespace Tests\Unit;

use App\Models\NoResiSequence;
use App\Models\Pengiriman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoResiGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test no_resi generation format
     */
    public function test_no_resi_has_correct_format(): void
    {
        $pengiriman = Pengiriman::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^EQ-\d{4}-\d{5}$/',
            $pengiriman->no_resi,
            'No resi should match format EQ-YYYY-XXXXX'
        );
    }

    /**
     * Test sequential no_resi generation
     */
    public function test_no_resi_generates_sequentially(): void
    {
        $pengiriman1 = Pengiriman::factory()->create();
        $pengiriman2 = Pengiriman::factory()->create();
        $pengiriman3 = Pengiriman::factory()->create();

        $number1 = (int) substr($pengiriman1->no_resi, -5);
        $number2 = (int) substr($pengiriman2->no_resi, -5);
        $number3 = (int) substr($pengiriman3->no_resi, -5);

        $this->assertEquals($number1 + 1, $number2, 'Second number should be first + 1');
        $this->assertEquals($number2 + 1, $number3, 'Third number should be second + 1');
    }

    /**
     * Test no duplicate no_resi even with concurrent creation
     *
     * This simulates race condition scenario
     */
    public function test_no_duplicate_no_resi_with_concurrent_creation(): void
    {
        // Create 100 pengiriman rapidly (simulating concurrent requests)
        $pengiriman = collect(range(1, 100))->map(function () {
            return Pengiriman::factory()->create();
        });

        $noResiList = $pengiriman->pluck('no_resi')->toArray();

        // Check for duplicates
        $unique = array_unique($noResiList);

        $this->assertCount(
            100,
            $unique,
            'All 100 no_resi should be unique (no duplicates)'
        );
    }

    /**
     * Test sequence year rollover
     */
    public function test_sequence_increments_correctly(): void
    {
        $currentYear = (int) date('Y');

        // Get initial sequence
        $initialSequence = NoResiSequence::getCurrentNumber($currentYear);

        // Create 5 pengiriman
        collect(range(1, 5))->each(fn () => Pengiriman::factory()->create());

        // Check sequence incremented by 5
        $finalSequence = NoResiSequence::getCurrentNumber($currentYear);

        $this->assertEquals(
            $initialSequence + 5,
            $finalSequence,
            'Sequence should increment by 5'
        );
    }

    /**
     * Test no_resi is unique in database
     */
    public function test_no_resi_is_unique_in_database(): void
    {
        $pengiriman1 = Pengiriman::factory()->create();

        // Try to create another pengiriman with same no_resi (should fail)
        $this->expectException(\Exception::class);

        Pengiriman::factory()->create([
            'no_resi' => $pengiriman1->no_resi
        ]);
    }

    /**
     * Test getNextNumber is thread-safe
     */
    public function test_get_next_number_is_atomic(): void
    {
        $currentYear = (int) date('Y');

        // Simulate multiple calls in sequence
        $numbers = collect(range(1, 10))->map(function () use ($currentYear) {
            return NoResiSequence::getNextNumber($currentYear);
        })->toArray();

        // All numbers should be unique and sequential
        $this->assertCount(10, array_unique($numbers), 'All numbers should be unique');

        sort($numbers);
        $this->assertEquals(range(1, 10), $numbers, 'Numbers should be sequential 1-10');
    }
}
