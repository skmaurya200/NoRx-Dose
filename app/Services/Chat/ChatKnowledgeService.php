<?php

namespace App\Services\Chat;

use App\Support\Settings\Site;
use Illuminate\Support\Str;

/**
 * The check that stands between a model's answer and a customer.
 *
 * The assistant is allowed to summarise, because "tell me about this product"
 * deserves a sentence rather than a copied paragraph. What it is not allowed
 * to do is add anything. So an answer is accepted only when the words in it
 * are words that were in the records it was given - and every figure in it
 * exactly so, because an invented delivery time or price is the one mistake
 * that costs a real customer something.
 *
 * This is a containment measure, not a proof. It stops the failure that
 * actually happens - a model filling a gap with something plausible - and it
 * fails closed: an answer it cannot verify is discarded and the visitor is
 * offered the contact form instead.
 */
class ChatKnowledgeService
{
    /**
     * The share of an answer's content words that must appear in the records.
     *
     * Low, and deliberately so. This is not a check on truthfulness - the
     * figure check below is - it is a sanity net that catches an answer about
     * something else entirely. At four fifths it was a paraphrasing check, and
     * it rejected perfectly good replies for the crime of sounding like a
     * person: "yes, we ship across the country" shares almost no words with
     * the record that says so.
     */
    private const MIN_WORD_OVERLAP = 0.35;

    /**
     * Pure function words - the ones that appear in every sentence and so tell
     * a search nothing.
     *
     * Deliberately much shorter than FREE_WORDS below. The two lists answer
     * different questions: "order" carries no claim inside an answer, but a
     * question containing it is almost certainly about ordering, and dropping
     * it from the search was why "how do I order?" matched nothing.
     *
     * @var list<string>
     */
    private const SEARCH_STOP_WORDS = [
        'a', 'about', 'all', 'am', 'an', 'and', 'any', 'are', 'as', 'at', 'be', 'been', 'but', 'by',
        'can', 'could', 'do', 'does', 'each', 'for', 'from', 'had', 'has', 'have', 'he', 'her', 'here',
        'his', 'i', 'if', 'in', 'into', 'is', 'it', 'its', 'just', 'may', 'me', 'my', 'no', 'not', 'of',
        'on', 'once', 'or', 'our', 'out', 'over', 'own', 'per', 'please', 'she', 'should', 'so', 'some',
        'such', 'than', 'that', 'the', 'their', 'them', 'then', 'there', 'these', 'they', 'this', 'those',
        'to', 'too', 'up', 'us', 'very', 'want', 'was', 'we', 'were', 'will', 'with', 'would', 'you', 'your',
        // The same handful in the transliterated Hindi customers actually type.
        'hai', 'ho', 'hoga', 'ka', 'ke', 'ki', 'ko', 'kya', 'mai', 'me', 'mera', 'meri', 'mujhe', 'se',
    ];

    /**
     * The words in a question worth searching on.
     *
     * @return list<string>
     */
    public function meaningfulTokens(string $text): array
    {
        return array_values(array_diff(
            array_unique($this->tokens($text)),
            $this->tokens(implode(' ', self::SEARCH_STOP_WORDS)),
        ));
    }

    /**
     * Words a summary may use freely because they carry no claim - if the
     * answer's only unsupported words are these, nothing was invented.
     *
     * @var list<string>
     */
    private const FREE_WORDS = [
        'a', 'about', 'all', 'also', 'an', 'and', 'any', 'are', 'as', 'at', 'available', 'be', 'been',
        'both', 'but', 'by', 'can', 'come', 'comes', 'contain', 'contains', 'do', 'does', 'each', 'every',
        'for', 'from', 'get', 'go', 'had', 'has', 'have', 'help', 'helps', 'her', 'here', 'his', 'how',
        'i', 'if', 'in', 'include', 'includes', 'including', 'into', 'is', 'it', 'its', 'just', 'made',
        'make', 'makes', 'many', 'may', 'more', 'most', 'need', 'no', 'not', 'of', 'on', 'once', 'one',
        'onto', 'or', 'order', 'other', 'our', 'out', 'over', 'own', 'per', 'product', 'products',
        'provide', 'provides', 'same', 'set', 'she', 'should', 'so', 'some', 'store', 'such', 'support',
        'take', 'takes', 'than', 'that', 'the', 'their', 'them', 'then', 'there', 'these', 'they', 'this',
        'those', 'through', 'to', 'too', 'up', 'use', 'used', 'uses', 'very', 'was', 'we', 'were', 'what',
        'when', 'where', 'which', 'while', 'who', 'will', 'with', 'within', 'without', 'you', 'your',
    ];

    /**
     * The records flattened into the text an answer is checked against.
     *
     * @param  list<array{title: string, body: string}>  $documents
     */
    public function context(array $documents): string
    {
        return collect($documents)
            ->map(fn (array $document) => trim(($document['title'] ?? '').' '.($document['body'] ?? '')))
            ->filter()
            ->implode("\n");
    }

    /**
     * Capitalised words that are not the name of anything this shop sells.
     */
    private const NOT_A_NAME = [
        'i', 'we', 'you', 'they', 'it', 'he', 'she', 'this', 'that', 'these', 'those', 'there', 'here',
        'yes', 'no', 'sorry', 'hi', 'hello', 'thanks', 'thank', 'please', 'our', 'your', 'my', 'the',
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /**
     * True when every name the answer drops is one the records actually carry.
     *
     * This is the check that matters once an answer is built from function
     * results rather than summarised from a page. A sentence about products is
     * ordinary prose - "that one cannot be ordered at the moment" shares almost
     * no words with a row of catalogue fields - so counting shared words here
     * throws away correct answers and catches nothing. What an answer must not
     * do is name a product, brand or category that was never returned, and that
     * is what this looks for: every capitalised name in the answer has to
     * appear in what the functions gave back.
     */
    public function namesOnlyKnownThings(string $answer, string $context): bool
    {
        $haystack = Str::lower(Str::ascii($context));

        foreach ($this->properNames($answer) as $name) {
            if (! str_contains($haystack, Str::lower(Str::ascii($name)))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The capitalised names in a piece of text, minus the ones grammar explains.
     *
     * A word that opens a sentence is capitalised by grammar rather than by
     * being a name, so a single one is skipped - but "Night Blend is calming"
     * keeps its name, because two capitalised words in a row are not grammar.
     *
     * @return list<string>
     */
    private function properNames(string $text): array
    {
        $allowed = array_merge(self::NOT_A_NAME, array_map(
            static fn (string $word): string => Str::lower($word),
            preg_split('/\s+/u', Site::name()) ?: [],
        ));

        $names = [];

        foreach (preg_split('/(?<=[.!?:;])\s+|\n+/u', trim($text)) ?: [] as $sentence) {
            $sentence = ltrim($sentence, " \t\"'([-");

            if (preg_match_all(
                '/\b\p{Lu}[\p{L}\p{N}]*(?:[\s-]\p{Lu}[\p{L}\p{N}]*|[\s-]\p{N}[\p{L}\p{N}]*)*/u',
                $sentence,
                $matches,
                PREG_OFFSET_CAPTURE,
            ) < 1) {
                continue;
            }

            foreach ($matches[0] as [$name, $offset]) {
                $name = trim($name);

                if ($offset === 0 && ! str_contains($name, ' ') && ! str_contains($name, '-')) {
                    continue;
                }

                if ($name !== '' && ! in_array(Str::lower($name), $allowed, true)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Things an answer is never allowed to be, whatever else is true of it.
     *
     * A reply goes into a chat bubble in front of a customer. Markup, a query,
     * or the shape of this application's own internals reaching that bubble is
     * either an attack that worked or a model that lost the thread, and both
     * are better shown as "I could not answer that" than printed.
     */
    private const NOT_PROSE = '/(?:<\/?[a-z][a-z0-9]*[\s>\/]'
        .'|\b(?:drop|truncate|delete|insert\s+into|update|select)\b[^.!?]{0,30}\b(?:table|from|into|set|where)\b'
        .'|\btbl_[a-z_]+\b'
        .'|\b(?:DB_PASSWORD|DB_USERNAME|DB_DATABASE|APP_KEY|GEMINI_API_KEY|api[_ ]?key)\b'
        .'|\b(?:system\s+(?:instruction|prompt)|these\s+rules|my\s+instructions)\b'
        // A link written as markup. Plain addresses the functions returned are
        // fine; [text](url) is formatting the widget cannot draw.
        .'|\[[^\]]+\]\(\s*[a-z]+:)/iu';

    private const EMAIL = '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu';

    private const LINK = '#\bhttps?://[^\s<>"\')]+#iu';

    /**
     * True when every email address and web link in the answer was handed to
     * the model by a function - the public contact details from Settings, a
     * product or page URL. Anything else was written by the model and would be
     * a made-up way of reaching this store. Phone numbers are figures and are
     * held to quotesOnlyKnownFigures().
     */
    public function mentionsOnlyKnownContacts(string $answer, string $context): bool
    {
        $context = mb_strtolower($context);

        foreach ([self::EMAIL, self::LINK] as $pattern) {
            preg_match_all($pattern, $answer, $found);

            foreach ($found[0] as $value) {
                if (! str_contains($context, mb_strtolower(rtrim($value, '.,;:!?')))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * True when the answer is something a person could be shown.
     *
     * Deliberately narrow. This is not a quality judgement - a dull answer
     * passes - it is the floor below which the text is not an answer at all.
     */
    public function readsAsProse(string $answer): bool
    {
        $answer = trim($answer);

        if ($answer === '' || ! str_contains($answer, ' ')) {
            return false;
        }

        // A serialised structure is this application's own shape, not a reply.
        if (str_starts_with($answer, '{') || str_starts_with($answer, '[')) {
            return false;
        }

        return preg_match(self::NOT_PROSE, $answer) !== 1;
    }

    /**
     * Language that only makes sense as a positive claim about this shop.
     *
     * Positive is the whole point. "We do not stock melatonin" is the honest
     * answer when a search came back empty, and refusing it left the visitor
     * with an apology instead of the truth; "we stock melatonin" is the
     * sentence that needs a record behind it. So a negation anywhere between
     * the pronoun and the verb exempts the phrase - which is what the tempered
     * gap below does.
     *
     * Bare topic words are deliberately not here. "In our catalogue" and
     * "delivery time" assert nothing on their own, and listing them refused
     * the one reply that was both true and useful: "I could not find
     * Paracetamol 500 mg in our catalogue". Only wording that is itself a
     * claim belongs in this pattern.
     */
    private const SHOP_CLAIM = '/(?:[$\x{00A3}\x{20AC}\x{20B9}]\s*\d'
        .'|\b\d+(?:\.\d+)?\s*(?:usd|dollars?|pounds?|euros?|rupees?)\b'
        .'|\b(?:we|our|us)\b(?:(?!\b(?:not|never|cannot|unable)\b|n\W?t\b)[^.!?]){0,40}'
        .'\b(?:sell|sells|stock|stocks|offer|offers|ship|ships|deliver|delivers'
        .'|charge|charges|refund|refunds|carry|carries|price|priced|prices|accept|accepts)\b'
        .'|\b(?:is|are|it\W?s|they\W?re)\s+in\s+stock\b'
        .'|\bfree\s+(?:shipping|delivery)\b)/iu';

    /**
     * True when every figure in the answer came from the records.
     *
     * The load-bearing half of grounds(), on its own. Used where there is
     * nothing to overlap with - an empty search result, say - but an invented
     * price or delivery time would still be an invented one.
     */
    public function quotesOnlyKnownFigures(string $answer, string $context): bool
    {
        $known = $this->figures($context);

        foreach ($this->figures($answer) as $figure) {
            if (! in_array($figure, $known, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * True when an unsourced answer has wandered into describing this shop.
     *
     * Used only for answers built without calling a single function. Those are
     * general knowledge and nothing verified them, so the one thing they must
     * not do is speak for this shop.
     */
    public function claimsShopFact(string $answer): bool
    {
        return preg_match(self::SHOP_CLAIM, $answer) === 1;
    }

    /**
     * True when everything the answer asserts came from the context.
     */
    public function grounds(string $answer, string $context): bool
    {
        $answerWords = $this->tokens($answer);

        if ($answerWords === []) {
            return false;
        }

        $contextWords = array_flip($this->tokens($context));

        // Figures are checked before anything else and without tolerance. A
        // price, a number of days or a percentage that is not in the records
        // is invented, whatever the rest of the sentence does. Compared as
        // whole figures, so "14" cannot pass by sitting inside "2014".
        $contextFigures = $this->figures($context);

        foreach ($this->figures($answer) as $figure) {
            if (! in_array($figure, $contextFigures, true)) {
                return false;
            }
        }

        // Stemmed the same way the answer is, or "products" would survive the
        // filter as "product" and be treated as a claim.
        $free = $this->tokens(implode(' ', self::FREE_WORDS));

        $claims = array_values(array_diff($answerWords, $free));

        if ($claims === []) {
            // Nothing but connective words: no claim was made, so there is
            // nothing to answer with either.
            return false;
        }

        $supported = count(array_filter(
            $claims,
            static fn (string $word) => isset($contextWords[$word]),
        ));

        return $supported / count($claims) >= self::MIN_WORD_OVERLAP;
    }

    /**
     * Lowercased, accent-folded, crudely stemmed words.
     *
     * The stemming is deliberately blunt: matching "capsules" against
     * "capsule" is the difference between accepting a fair summary and
     * rejecting it over a plural. It mis-stems words like "boxes", which the
     * overlap threshold below absorbs.
     *
     * Public because the lexical half of retrieval scores questions against
     * records with exactly the same notion of a word; two different ones would
     * mean an answer could be grounded by a token the search never saw.
     *
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $text = Str::lower(Str::ascii($text));

        preg_match_all('/[a-z][a-z0-9]*/', $text, $matches);

        return array_map(static function (string $word): string {
            // Longest suffix first, and never down to a stub: "need" must stay
            // "need" rather than becoming "ne".
            foreach (['ing', 'ed', 's'] as $suffix) {
                if (strlen($word) > strlen($suffix) + 2 && str_ends_with($word, $suffix)) {
                    return substr($word, 0, -strlen($suffix));
                }
            }

            return $word;
        }, $matches[0]);
    }

    /**
     * Every run of digits in the text, with separators removed so "1,200" and
     * "1200" are the same figure.
     *
     * @return list<string>
     */
    private function figures(string $text): array
    {
        preg_match_all('/\d[\d,._]*/', $text, $matches);

        return array_values(array_unique(array_filter(array_map(
            fn (string $figure) => $this->digits($figure),
            $matches[0],
        ))));
    }

    private function digits(string $text): string
    {
        return (string) preg_replace('/[^0-9]/', '', $text);
    }
}
