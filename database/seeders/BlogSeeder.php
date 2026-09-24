<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The journal the storefront shipped with.
 *
 * These six posts used to be a hard-coded array in HomeController. Moving them
 * here means the pages have something to render on a fresh install while every
 * one of them is now an ordinary row an editor can rewrite or delete.
 *
 * Keyed by slug through updateOrCreate, so running the seeder twice updates
 * the same rows rather than building a second journal.
 */
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];

        foreach ($this->categories() as $position => $name) {
            $categories[$name] = BlogCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $position + 1,
                ],
            );
        }

        foreach ($this->posts() as $index => $item) {
            BlogPost::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id' => $categories[$item['category']]->id,
                    'title' => $item['title'],
                    'excerpt' => $item['excerpt'],
                    // Paragraphs become the markup the editor would have
                    // produced, which is what the sanitiser allows through.
                    'body' => '<p>'.implode('</p><p>', $item['body']).'</p>',
                    'takeaways' => $item['takeaways'],
                    'author_name' => 'The Aurum team',
                    'read_minutes' => $item['read'],
                    'status' => 'published',
                    // The newest post leads the journal until an editor picks
                    // another one.
                    'is_featured' => $index === 0,
                    'published_at' => $item['date'],
                ],
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function categories(): array
    {
        return ['Ingredients', 'Routines', 'Behind the brand'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function posts(): array
    {
        return [
            [
                'slug' => 'reading-an-ingredient-list',
                'title' => 'How to read an ingredient list without a chemistry degree',
                'excerpt' => 'A short guide to the names you keep seeing on the back of the bottle, and which ones actually matter.',
                'category' => 'Ingredients',
                'read' => 6,
                'date' => '2026-08-12 09:00:00',
                'body' => [
                    'Every bottle carries a list that reads like an exam paper. The good news is that the order is not random: ingredients are listed by weight, so whatever sits in the first third of the list is doing most of the work.',
                    'The names themselves are standardised, which is why the same plant extract can appear under a Latin name on one label and a common name on another. Neither version tells you how much is in there, so the position on the list matters more than the name does.',
                    'What to look for is a stated amount next to the ingredients a product is sold on. If a formula is marketed on one active and that active has no quantity beside it, there is usually a reason.',
                    'The rest of the list is normally there to keep the product stable, spreadable or drinkable. Those ingredients are not filler in a pejorative sense - a capsule that separates on the shelf is no use to anyone.',
                ],
                'takeaways' => [
                    'Ingredients are ordered by weight, so position tells you a lot.',
                    'A stated amount beside an active is worth more than a long name.',
                    'Stabilisers and carriers are doing a real job, not padding the list.',
                ],
            ],
            [
                'slug' => 'building-a-routine',
                'title' => 'Building a routine you will actually keep up with',
                'excerpt' => 'Five steps beats fifteen. Here is how to strip a routine back to what is doing the work.',
                'category' => 'Routines',
                'read' => 8,
                'date' => '2026-08-02 09:00:00',
                'body' => [
                    'The most common reason a routine fails is not the products in it. It is the number of them. A fifteen-step evening is a plan for a quiet week, and most weeks are not quiet.',
                    'Start by writing down what you already do without thinking. That is your real baseline, and anything you add sits on top of it. Two additions is usually the most a baseline will absorb at once.',
                    'Give each addition four weeks before you judge it. Most formulas are measured over eight to twelve weeks in testing, so a fortnight tells you almost nothing except whether it irritates.',
                    'When something has to go, drop the step you would not notice missing on a rushed morning. That question sorts a routine faster than any ranking.',
                ],
                'takeaways' => [
                    'Add at most two steps to an existing routine at a time.',
                    'Give a new product four weeks before deciding.',
                    'Cut the step you would not miss on a rushed morning.',
                ],
            ],
            [
                'slug' => 'what-batch-testing-means',
                'title' => 'What batch testing means, and why we publish the results',
                'excerpt' => 'Every batch gets an independent report. We explain what is measured and where to find it.',
                'category' => 'Behind the brand',
                'read' => 5,
                'date' => '2026-07-24 09:00:00',
                'body' => [
                    'Batch testing means a sample from each production run is sent to a laboratory that has no stake in the result. The report comes back with what was found, not with what we hoped would be found.',
                    'Two things get measured. The first is identity and strength: is the active present, and at the amount printed on the label. The second is contaminants, which covers heavy metals, microbes and residual solvents.',
                    'We publish the report against the batch code stamped on the base of every bottle, so the document you are reading matches the product in your hand rather than a representative sample from last year.',
                    'When a batch fails, it does not ship. That is the entire point of testing before release rather than after a complaint.',
                ],
                'takeaways' => [
                    'Each production run is sampled and tested independently.',
                    'Reports cover both strength and contaminants.',
                    'The batch code on the bottle matches the published report.',
                ],
            ],
            [
                'slug' => 'supplements-and-timing',
                'title' => 'Does it matter what time of day you take a supplement?',
                'excerpt' => 'Sometimes yes, often no. The short answer depends on whether the active needs food to be absorbed.',
                'category' => 'Ingredients',
                'read' => 7,
                'date' => '2026-07-15 09:00:00',
                'body' => [
                    'Timing advice is one of the most repeated and least explained pieces of supplement guidance. The useful version of the rule is narrower than it sounds.',
                    'Fat-soluble actives are absorbed better alongside a meal that contains some fat, which is why the label often says "with food". That is a genuine absorption difference rather than a comfort suggestion.',
                    'Water-soluble actives are far less fussy. For those, the best time is whichever time you will remember, because a dose you skip is worse than a dose taken at a theoretically suboptimal hour.',
                    'The exception worth respecting is anything that affects sleep or alertness. Those belong at a fixed point in the day, and the label will say so.',
                ],
                'takeaways' => [
                    'Fat-soluble actives do better alongside a meal.',
                    'Water-soluble actives are flexible - pick a time you will keep.',
                    'Anything affecting sleep gets a fixed slot in the day.',
                ],
            ],
            [
                'slug' => 'packaging-without-plastic',
                'title' => 'Getting to packaging that is genuinely recyclable',
                'excerpt' => 'Mixed materials are the reason so much "recyclable" packaging never gets recycled. Here is what we changed.',
                'category' => 'Behind the brand',
                'read' => 6,
                'date' => '2026-07-03 09:00:00',
                'body' => [
                    'A carton lined with foil is recyclable in the sense that both materials can be recycled. In practice the two cannot be separated at kerbside, so the whole thing goes to landfill.',
                    'That is the trap most packaging claims fall into. The fix is boring: use one material per component, and make any component that must differ easy to pull off by hand.',
                    'Our bottles are a single polymer with a label in the same family, so the whole unit can go into one stream without anyone having to disassemble it first.',
                    'The outer box is uncoated board with a paper tape seal. It costs a little more than a plastic-taped box and it survives the same journey.',
                ],
                'takeaways' => [
                    'Mixed materials are the main reason recyclable packaging is not recycled.',
                    'One material per component beats a clever composite.',
                    'Anything that must differ should come apart by hand.',
                ],
            ],
            [
                'slug' => 'skincare-myths',
                'title' => 'Four skincare claims that do not survive a close reading',
                'excerpt' => 'Chemical-free, clinically proven, dermatologist approved - what these phrases are allowed to mean.',
                'category' => 'Routines',
                'read' => 9,
                'date' => '2026-06-21 09:00:00',
                'body' => [
                    'Marketing language on a bottle sits inside rules, but the rules are looser than most people assume. Four phrases in particular do a lot of work while promising very little.',
                    '"Chemical-free" is the easiest one. Water is a chemical. What the phrase usually means is that a specific family of ingredients has been left out, and the label rarely says which.',
                    '"Clinically proven" tells you a study happened. It does not tell you how many people were in it, how long it ran, or whether there was anything to compare against.',
                    '"Dermatologist approved" and "dermatologist tested" are different claims, and the second one is the weaker of the two. Tested only means a test occurred - the result is a separate question.',
                ],
                'takeaways' => [
                    '"Chemical-free" describes an omission, not a property.',
                    '"Clinically proven" says a study happened, not what it showed.',
                    '"Tested" is a weaker claim than "approved".',
                ],
            ],
        ];
    }
}
