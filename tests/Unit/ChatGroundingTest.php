<?php

namespace Tests\Unit;

use App\Services\Chat\ChatKnowledgeService;
use PHPUnit\Framework\TestCase;

/**
 * The rule that decides whether an answer may be shown to a customer.
 *
 * A unit test because this is pure text arithmetic with no framework in it,
 * and because it is the single point where "the assistant must not invent
 * things" is actually enforced - every case worth arguing about belongs here
 * rather than behind an HTTP round trip.
 */
class ChatGroundingTest extends TestCase
{
    private ChatKnowledgeService $knowledge;

    private string $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->knowledge = new ChatKnowledgeService;

        $this->context = 'Standard delivery arrives in 3 to 5 working days. '
            .'Express delivery arrives in 1 to 2 working days. '
            .'Returns are accepted within 30 days of receipt.';
    }

    /* ------------------------------------------------------------ accepted */

    public function test_accepts_a_summary_written_from_the_records(): void
    {
        $this->assertTrue($this->knowledge->grounds(
            'Standard delivery arrives in 3 to 5 working days and express in 1 to 2.',
            $this->context,
        ));
    }

    public function test_accepts_a_reworded_summary_that_adds_no_facts(): void
    {
        // Not a copy of any sentence: the words are reordered and joined, which
        // is exactly what a summary is allowed to do.
        $this->assertTrue($this->knowledge->grounds(
            'Returns are accepted within 30 days, and express delivery arrives in 1 to 2 working days.',
            $this->context,
        ));
    }

    public function test_accepts_a_plural_where_the_records_use_a_singular(): void
    {
        $this->assertTrue($this->knowledge->grounds(
            'Express delivery arrives in 1 working day.',
            'Express delivery arrives in 1 working days.',
        ));
    }

    /* ------------------------------------------------------------ refused */

    public function test_refuses_a_figure_that_is_not_in_the_records(): void
    {
        // The only wrong word is the number, and it is the one that would cost
        // a customer something.
        $this->assertFalse($this->knowledge->grounds(
            'Standard delivery arrives in 3 to 9 working days.',
            $this->context,
        ));
    }

    public function test_refuses_a_figure_hiding_inside_a_longer_one(): void
    {
        $this->assertFalse($this->knowledge->grounds(
            'Returns are accepted within 3 days of receipt.',
            'Returns are accepted within 30 days of receipt.',
        ));
    }

    public function test_refuses_an_invented_policy(): void
    {
        $this->assertFalse($this->knowledge->grounds(
            'Every order ships free overnight with a lifetime money-back guarantee.',
            $this->context,
        ));
    }

    public function test_refuses_an_answer_made_only_of_filler(): void
    {
        // No claim at all is not an answer; it is a sentence about nothing.
        $this->assertFalse($this->knowledge->grounds('It can be, and it may also.', $this->context));
    }

    public function test_refuses_an_empty_answer(): void
    {
        $this->assertFalse($this->knowledge->grounds('', $this->context));
        $this->assertFalse($this->knowledge->grounds('Standard delivery arrives quickly.', ''));
    }

    /* ------------------------------------------------------------- context */

    public function test_the_context_is_every_record_it_was_given(): void
    {
        $context = $this->knowledge->context([
            ['title' => 'Delivery', 'body' => 'Arrives in 3 days.'],
            ['title' => 'Returns', 'body' => 'Within 30 days.'],
        ]);

        $this->assertStringContainsString('Delivery Arrives in 3 days.', $context);
        $this->assertStringContainsString('Returns Within 30 days.', $context);
    }

    public function test_meaningful_tokens_drop_only_pure_function_words(): void
    {
        $tokens = $this->knowledge->meaningfulTokens('What is your delivery time for an order?');

        $this->assertContains('delivery', $tokens);
        $this->assertContains('time', $tokens);

        // "order" stays. It appears in most of the shop's own guidance, which
        // is exactly why the search weights it down rather than throwing it
        // away - a question about an order is about ordering, and dropping the
        // word was why "how do I order?" used to match nothing.
        $this->assertContains('order', $tokens);

        // These carry nothing at all.
        $this->assertNotContains('is', $tokens);
        $this->assertNotContains('your', $tokens);
        $this->assertNotContains('for', $tokens);
        $this->assertNotContains('an', $tokens);
    }
}
