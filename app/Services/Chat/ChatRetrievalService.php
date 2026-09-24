<?php

namespace App\Services\Chat;

use App\Models\ChatEmbedding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Finds the records in the store that a question is actually about.
 *
 * The question is embedded and compared to every indexed record by cosine
 * similarity, which is what lets "something to help me sleep" reach a product
 * whose description never says "sleep" - the thing a LIKE query cannot do.
 *
 * The whole index is scored in PHP. That is the right trade at this size: a
 * few hundred vectors is a millisecond of arithmetic, and it keeps the schema
 * portable instead of depending on a vector index that community MySQL does
 * not have. The index is cached between requests because loading it is the
 * expensive half, not the maths.
 */
class ChatRetrievalService
{
    /**
     * Below this share of a question's meaningful words, a lexical match is a
     * coincidence rather than a match.
     */
    private const MIN_LEXICAL_OVERLAP = 0.5;

    /**
     * The longest run of words a phrase match will look for, and what each
     * word of the longest run found is worth on top of the overlap score.
     */
    private const MAX_PHRASE = 5;

    private const PHRASE_WEIGHT = 0.1;

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly ChatCorpusService $corpus,
        private readonly ChatKnowledgeService $knowledge,
    ) {}

    /**
     * The best matches for a question, strongest first.
     *
     * An empty list is a real answer: it means the store has nothing to say
     * about this, and the caller should offer the contact form rather than
     * letting the model improvise.
     *
     * @return list<array{ref: string, type: string, source_id: int|null, title: string, body: string, url: string|null, score: float}>
     */
    public function search(string $question, ?int $limit = null): array
    {
        $question = trim($question);

        if ($question === '') {
            return [];
        }

        $limit = max(1, $limit ?? (int) config('chat.retrieval.top_k'));
        $index = $this->index();
        $vector = null;

        if ($index !== [] && $this->gemini->isConfigured()) {
            try {
                $vector = $this->gemini->embed([$question], GeminiService::TASK_QUERY)[0] ?? null;
            } catch (Throwable $exception) {
                // A failed embedding is not the same as the store having no
                // answer, so this drops to the word-overlap search below
                // rather than telling the visitor there is nothing.
                Log::warning('Chat retrieval embedding failed.', ['error_type' => $exception::class]);
            }
        }

        if ($vector === null) {
            return $this->lexical($question, $limit);
        }

        /* Anything added or rewritten since the last indexing run has no
           vector yet, so meaning cannot reach it. Its words still can, and a
           product added this morning has to be findable this morning - so the
           tail the index has not caught up with is searched by word and ranked
           underneath. */
        $semantic = $this->semantic($vector, $index, $limit);

        return $this->best(
            array_merge($semantic, $this->unindexed($question, $index, $semantic, $limit)),
            $limit,
        );
    }

    /**
     * Word matches among the records the index has not caught up with.
     *
     * Ranked below every semantic match rather than among them, because the
     * two numbers do not mean the same thing: one is the cosine of two
     * embeddings, the other a share of weighted words. Comparing them by value
     * would let a product typed in this morning outrank a genuinely better
     * match for no reason but the arithmetic.
     *
     * @param  list<array<string, mixed>>  $index
     * @param  list<array<string, mixed>>  $semantic
     * @return list<array<string, mixed>>
     */
    private function unindexed(string $question, array $index, array $semantic, int $limit): array
    {
        $embedded = array_flip(array_column($index, 'ref'));

        $fresh = array_values(array_filter(
            $this->lexical($question, $limit),
            static fn (array $match): bool => ! isset($embedded[$match['ref']]),
        ));

        if ($fresh === []) {
            return [];
        }

        // Spread through the room below the weakest semantic match, keeping
        // the order the word search put them in.
        $lowest = $semantic === [] ? 1.0 : min(array_column($semantic, 'score'));
        $step = $lowest / (count($fresh) + 1);

        return array_map(
            static fn (array $match, int $position): array => array_replace($match, [
                'score' => round($lowest - $step * ($position + 1), 6),
            ]),
            $fresh,
            array_keys($fresh),
        );
    }

    /**
     * Cosine similarity against the embedded index.
     *
     * @param  list<float>  $vector
     * @param  list<array<string, mixed>>  $index
     * @return list<array<string, mixed>>
     */
    private function semantic(array $vector, array $index, int $limit): array
    {
        $minimum = (float) config('chat.retrieval.min_score');
        $norm = $this->norm($vector);

        if ($norm === 0.0) {
            return [];
        }

        $scored = [];

        foreach ($index as $record) {
            if (count($record['vector']) !== count($vector)) {
                // Indexed under a different model or dimensionality; not
                // comparable, and pretending otherwise scores nonsense.
                continue;
            }

            $score = $this->cosine($vector, $record['vector'], $norm, $record['norm']);

            if ($score < $minimum) {
                continue;
            }

            unset($record['vector'], $record['norm']);

            $scored[] = $record + ['score' => round($score, 6)];
        }

        return $this->best($scored, $limit);
    }

    /**
     * Weighted word overlap against the corpus itself.
     *
     * The fallback for a store that has not run `chat:index` yet, and for the
     * minutes when the provider is unreachable. It finds far less than the
     * embedded search does - "something for tired afternoons" matches nothing
     * here - but it means a shop still answers its own FAQ and its own "how do
     * I order?" out of the box rather than sending every visitor to a form.
     *
     * Words are weighted by how rare they are, because counting them equally
     * does not work: "what", "show" and "order" appear in most records, so a
     * plain overlap score ties every guide with every other one and the winner
     * is whichever happened to be built first. Rarity is what makes "charges"
     * decide a question about charges.
     *
     * @return list<array<string, mixed>>
     */
    private function lexical(string $question, int $limit): array
    {
        $wanted = $this->knowledge->meaningfulTokens($question);

        if ($wanted === []) {
            return [];
        }

        $prepared = $this->corpus();

        if ($prepared === []) {
            return [];
        }

        $weights = $this->weights($wanted, $prepared);
        $total = array_sum($weights);

        if ($total <= 0.0) {
            return [];
        }

        // The question as a stemmed sequence, for the phrase check below. Stop
        // words are kept here: "how do i order" is a phrase, and dropping half
        // of it would stop it being one.
        $sequence = $this->knowledge->tokens($question);

        $scored = [];

        foreach ($prepared as $document) {
            $hit = 0.0;

            foreach ($wanted as $word) {
                if (isset($document['tokens'][$word])) {
                    $hit += $weights[$word];
                }
            }

            $score = $hit / $total;

            // The floor is applied before the phrase bonus, so the bonus only
            // reorders records that already matched.
            if ($score < self::MIN_LEXICAL_OVERLAP) {
                continue;
            }

            $record = $document['record'];

            $scored[] = [
                'ref' => $record['source_type'].':'.$record['source_key'],
                'type' => $record['source_type'],
                'source_id' => $record['source_id'],
                'title' => $record['title'],
                'body' => $record['body'],
                'url' => $record['url'],
                'score' => round($score + $this->phraseBonus($sequence, $document['text']), 6),
            ];
        }

        return $this->best($scored, $limit);
    }

    /**
     * How much each of the question's words is worth.
     *
     * Inverse document frequency, smoothed so nothing is ever worth zero: a
     * word in one record out of two hundred decides a match, a word in half of
     * them barely nudges it.
     *
     * @param  list<string>  $wanted
     * @param  list<array<string, mixed>>  $prepared
     * @return array<string, float>
     */
    private function weights(array $wanted, array $prepared): array
    {
        $count = count($prepared);
        $weights = [];

        foreach ($wanted as $word) {
            $frequency = 0;

            foreach ($prepared as $document) {
                if (isset($document['tokens'][$word])) {
                    $frequency++;
                }
            }

            $weights[$word] = log(($count + 1) / ($frequency + 1)) + 1.0;
        }

        return $weights;
    }

    /**
     * Credit for matching a run of the question rather than scattered words.
     *
     * "order kaise kare" appears verbatim in the ordering guide and nowhere
     * else, while its individual words appear in three guides - which is the
     * difference between answering the question and answering a neighbour of
     * it. Longer runs are worth more, up to a cap.
     *
     * @param  list<string>  $sequence  the question, stemmed, in order
     */
    private function phraseBonus(array $sequence, string $text): float
    {
        $longest = 0;

        for ($length = min(self::MAX_PHRASE, count($sequence)); $length >= 2; $length--) {
            for ($start = 0; $start + $length <= count($sequence); $start++) {
                if (str_contains($text, implode(' ', array_slice($sequence, $start, $length)))) {
                    $longest = $length;

                    break 2;
                }
            }
        }

        return $longest >= 2 ? self::PHRASE_WEIGHT * $longest : 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $scored
     * @return list<array<string, mixed>>
     */
    private function best(array $scored, int $limit): array
    {
        // Score first; on a tie, whatever is more specific. A guide mentions
        // "buy" and so does half the catalogue - when both match a question
        // equally, the product is what the visitor meant.
        $rank = array_flip(['product', 'faq', 'page', 'post', 'category', 'guide']);

        usort($scored, static fn (array $a, array $b) => [$b['score'], $rank[$a['type']] ?? 9]
            <=> [$a['score'], $rank[$b['type']] ?? 9]);

        return array_slice($scored, 0, $limit);
    }

    /**
     * The corpus, tokenised and cached.
     *
     * Both halves are cached together for the same span as the vector index:
     * building the corpus walks the catalogue and tokenising it walks every
     * word of it, and doing either once per chat message would make the
     * fallback more expensive than the thing it stands in for.
     *
     * @return list<array{record: array<string, mixed>, tokens: array<string, int>, text: string}>
     */
    private function corpus(): array
    {
        return Cache::remember(
            ChatIndexService::CACHE_KEY.':corpus',
            max(1, (int) config('chat.retrieval.cache_seconds')),
            function (): array {
                return array_map(function (array $record): array {
                    $tokens = $this->knowledge->tokens($record['title'].' '.$record['body']);

                    return [
                        'record' => $record,
                        // Flipped for isset() lookups rather than in_array().
                        'tokens' => array_flip($tokens),
                        // The same words as one string, which is what a phrase
                        // is searched for in.
                        'text' => implode(' ', $tokens),
                    ];
                }, $this->corpus->all());
            },
        );
    }

    /**
     * The best matches regardless of how weak they are.
     *
     * The last thing tried before apologising. A weak match is not a licence
     * to answer from it - the model is told to say when the records do not
     * cover something, and every figure is still checked - but it is better
     * than handing the visitor an apology while the answer sits in the index
     * one point below the floor.
     *
     * @return list<array<string, mixed>>
     */
    public function searchLoose(string $question, int $limit = 3): array
    {
        $floor = config('chat.retrieval.min_score');

        config(['chat.retrieval.min_score' => 0.0]);

        try {
            return $this->search($question, $limit);
        } finally {
            config(['chat.retrieval.min_score' => $floor]);
        }
    }

    /**
     * Only the matches worth grounding an answer on.
     *
     * @param  list<array<string, mixed>>  $matches
     * @return list<array{title: string, body: string}>
     */
    public function documents(array $matches, ?int $limit = null): array
    {
        $limit = $limit ?? (int) config('chat.retrieval.context_documents');

        return array_map(
            static fn (array $match) => ['title' => $match['title'], 'body' => $match['body']],
            array_slice($matches, 0, max(1, $limit)),
        );
    }

    /**
     * The first match of a given type, which is how a product question finds
     * the product it is about.
     *
     * @param  list<array<string, mixed>>  $matches
     * @return array<string, mixed>|null
     */
    public function firstOfType(array $matches, string $type): ?array
    {
        foreach ($matches as $match) {
            if ($match['type'] === $type) {
                return $match;
            }
        }

        return null;
    }

    /**
     * True when the embedded index is in play, rather than the word-overlap
     * fallback. Worth knowing in the panel; the answer path does not care.
     */
    public function isAvailable(): bool
    {
        return $this->gemini->isConfigured() && ChatEmbedding::query()->exists();
    }

    /**
     * Every indexed record with its vector and precomputed norm.
     *
     * The norm is stored with the record because it does not depend on the
     * question: computing it once per cache fill rather than once per record
     * per message is most of the cost of this class.
     *
     * @return list<array<string, mixed>>
     */
    private function index(): array
    {
        $seconds = max(1, (int) config('chat.retrieval.cache_seconds'));

        $vectors = Cache::remember(ChatIndexService::CACHE_KEY, $seconds, fn (): array => ChatEmbedding::query()
            ->get(['source_type', 'source_key', 'vector'])
            ->mapWithKeys(fn (ChatEmbedding $row): array => [
                $row->source_type.':'.$row->source_key => array_map(
                    static fn (mixed $value): float => (float) $value,
                    $row->vector ?? [],
                ),
            ])
            ->all());

        $index = [];

        foreach ($this->corpus() as $entry) {
            $record = $entry['record'];
            $ref = $record['source_type'].':'.$record['source_key'];
            $vector = $vectors[$ref] ?? [];
            $norm = $this->norm($vector);

            if ($norm <= 0.0) {
                continue;
            }

            $index[] = [
                'ref' => $ref,
                'type' => $record['source_type'],
                'source_id' => $record['source_id'],
                'title' => $record['title'],
                'body' => $record['body'],
                'url' => $record['url'],
                'vector' => $vector,
                'norm' => $norm,
            ];
        }

        return $index;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosine(array $a, array $b, float $normA, float $normB): float
    {
        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        $dot = 0.0;

        foreach ($a as $index => $value) {
            $dot += $value * $b[$index];
        }

        return $dot / ($normA * $normB);
    }

    /**
     * @param  list<float>  $vector
     */
    private function norm(array $vector): float
    {
        $sum = 0.0;

        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        return sqrt($sum);
    }
}
