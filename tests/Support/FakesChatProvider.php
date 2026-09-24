<?php

namespace Tests\Support;

/**
 * The two shapes a chat provider reply can take.
 *
 * The assistant reaches this shop's data by asking for it: the model answers
 * with either a request to run one of the shop's functions, or with the words
 * to say. Tests fake whichever of the two the case under test needs, in the
 * order the conversation would produce them.
 */
trait FakesChatProvider
{
    /**
     * The model asking this application to run one of its functions.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function aiCall(string $name, array $arguments = [], int $tokens = 30): array
    {
        return [
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['functionCall' => ['name' => $name, 'args' => $arguments]]]],
            ]],
            'usageMetadata' => ['totalTokenCount' => $tokens],
        ];
    }

    /**
     * The model answering through sendReply - its reply, its own suggestions,
     * the products to show and whether it asked for a way to reach the visitor.
     *
     * @param  list<string>  $suggestions
     * @param  list<int>  $productIds
     * @return array<string, mixed>
     */
    protected function aiReply(
        string $answer,
        array $suggestions = [],
        array $productIds = [],
        bool $needsContact = false,
        string $intent = 'general_question',
        int $tokens = 30,
    ): array {
        return $this->aiCall('sendReply', [
            'answer' => $answer,
            'suggestions' => $suggestions,
            'product_ids' => $productIds,
            'needs_contact' => $needsContact,
            'intent' => $intent,
        ], $tokens);
    }

    /**
     * The model writing the reply as plain text instead of through sendReply.
     *
     * @return array<string, mixed>
     */
    protected function aiText(string $text, int $tokens = 30): array
    {
        return [
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => $text]]],
            ]],
            'usageMetadata' => ['totalTokenCount' => $tokens],
        ];
    }
}
