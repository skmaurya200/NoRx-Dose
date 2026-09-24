<?php

namespace Tests\Unit;

use App\Services\Chat\ChatAnswerService;
use App\Services\Chat\ChatKnowledgeService;
use App\Services\Chat\ChatToolService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The routing patterns, checked for the one fault that hides.
 *
 * Twice now a pattern in this application has been written by a script where
 * "\b" is an escape for the backspace character rather than two characters of
 * regular expression. The result compiles, matches nothing, and fails silently:
 * the guard it was guarding simply stops happening, and every test that does
 * not exercise that exact branch still passes. CONTEXTUAL spent a whole round
 * of fixes doing nothing at all.
 *
 * So the patterns are read back here and checked for bytes no regular
 * expression of ours contains.
 */
class ChatPatternTest extends TestCase
{
    /**
     * @return list<array{string, string}>
     */
    public static function patterns(): array
    {
        $found = [];

        foreach ([ChatAnswerService::class, ChatKnowledgeService::class, ChatToolService::class] as $class) {
            foreach ((new ReflectionClass($class))->getConstants() as $name => $value) {
                if (is_string($value) && str_starts_with($value, '/')) {
                    $found[] = [$class.'::'.$name, $value];
                }
            }
        }

        return $found;
    }

    #[DataProvider('patterns')]
    public function test_a_pattern_carries_no_control_characters(string $name, string $pattern): void
    {
        $this->assertSame(
            0,
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $pattern),
            $name.' contains a control character - almost certainly a "\b" that a script turned into a '
                .'backspace. The pattern will compile and match nothing.',
        );
    }

    /**
     * And that each one still compiles, which a stray byte can also break.
     */
    #[DataProvider('patterns')]
    public function test_a_pattern_compiles(string $name, string $pattern): void
    {
        $this->assertNotFalse(@preg_match($pattern, 'a harmless sentence'), $name.' does not compile.');
    }
}
