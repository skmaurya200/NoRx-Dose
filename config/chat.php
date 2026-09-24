<?php

return [
    'enabled' => (bool) env('CHAT_ENABLED', true),
    'requests_per_minute' => (int) env('CHAT_REQUESTS_PER_MINUTE', 15),
    'ip_requests_per_minute' => (int) env('CHAT_IP_REQUESTS_PER_MINUTE', 60),
    'contacts_per_hour' => (int) env('CHAT_CONTACTS_PER_HOUR', 5),

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    |
    | The chat answers from an index of the store rather than from the model's
    | own memory. Every indexable record is embedded once and compared to the
    | embedded question by cosine similarity, which is what lets "I want to buy
    | something for sleep" reach a product whose description never uses the
    | word "sleep".
    |
    | min_score is the floor below which a match is treated as no match. Too
    | low and the assistant answers confidently from an unrelated page; that is
    | worse than handing the visitor the contact form.
    |
    */

    'retrieval' => [
        'embedding_model' => env('CHAT_EMBEDDING_MODEL', 'gemini-embedding-001'),

        // 768 keeps the index a third of the size of the default 3072 at
        // effectively the same recall on a catalogue this size.
        'dimensions' => (int) env('CHAT_EMBEDDING_DIMENSIONS', 768),

        // Records per outbound batch when indexing.
        'batch_size' => (int) env('CHAT_EMBEDDING_BATCH', 32),

        // How many matches are retrieved, and how many of them are allowed to
        // become grounding context for one answer.
        'top_k' => (int) env('CHAT_RETRIEVAL_TOP_K', 10),

        // More of what was found reaches the model than used to. Three records
        // was enough to quote from and not enough to answer from: a question
        // that straddled two of them came back as "I don't have that".
        'context_documents' => (int) env('CHAT_RETRIEVAL_CONTEXT', 6),

        /*
         | The floor below which a match is treated as no match.
         |
         | Measured against this catalogue rather than guessed at. Questions
         | this shop can answer score from 0.66 up ("pain killer medicine
         | batao" 0.667, "How do I pay?" 0.732); questions it has no business
         | answering top out around 0.59 ("who won the world cup" 0.536, "what
         | is melatonin" 0.591, which is general knowledge and not a product).
         | 0.62 sits in the gap.
         |
         | Below it nothing is retrieved, the deterministic shortcuts have
         | nothing to fire on, and the question reaches the model with the
         | shop's functions - which answers it as general knowledge or says it
         | cannot help, instead of reciting the payment steps at somebody
         | asking about cricket. That is what a floor of 0.45 used to do.
         |
         | Recalibrate if the embedding model changes: this is a similarity
         | score and the scale is the model's, not ours.
         */
        'min_score' => (float) env('CHAT_RETRIEVAL_MIN_SCORE', 0.62),

        // The whole index is loaded to score one question, so it is cached.
        // Re-indexing forgets the key rather than waiting this out.
        'cache_seconds' => (int) env('CHAT_RETRIEVAL_CACHE_SECONDS', 900),

        // Characters of a record that get embedded. Beyond this a product
        // description stops describing the product and starts diluting it.
        'max_body_characters' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Answering
    |--------------------------------------------------------------------------
    |
    | Answers are short on purpose: this is a chat bubble, not a product page.
    | The model may summarise, but only from the retrieved records - see
    | ChatAnswerService for how that is enforced.
    |
    */

    'answer' => [
        // Room for a real answer. Sixty words is a sentence and a half, which
        // is why replies read as clipped rather than helpful.
        'max_words' => (int) env('CHAT_ANSWER_MAX_WORDS', 120),

        // Turns of the conversation sent with the question, so "and the bigger
        // one?" resolves to something.
        'history_turns' => (int) env('CHAT_HISTORY_TURNS', 8),
        'products_per_page' => 5,

        // How many times the model may ask this application for data before it
        // has to write something. Three covers a real question - find the
        // product, then read its detail - without letting a confused turn
        // spend the shop's budget in a loop.
        'tool_rounds' => (int) env('CHAT_TOOL_ROUNDS', 3),

        // Turns of the conversation that go with a tool-assisted question.
        // Shorter than the history above on purpose: the functions carry the
        // facts, so history only has to carry what "that one" refers to.
        'assist_history_turns' => (int) env('CHAT_ASSIST_HISTORY_TURNS', 4),
    ],

    /*
    |--------------------------------------------------------------------------
    | Human handover
    |--------------------------------------------------------------------------
    */

    'default_reply_mode' => env('CHAT_DEFAULT_REPLY_MODE', 'ai'),

    // How often the open widget asks for messages it has not seen. Only while
    // the panel is open, and only after a reply is outstanding.
    'poll_seconds' => (int) env('CHAT_POLL_SECONDS', 8),

    /*
    |--------------------------------------------------------------------------
    | Messages that are not answers
    |--------------------------------------------------------------------------
    |
    | Every reply to a visitor is written by the model - there is no stored
    | greeting, answer or suggestion. These two are the only fixed words the
    | chat can show, and neither answers a question:
    |
    | failure_message   shown only when the model itself cannot be reached or
    |                   its replies fail the checks twice. It cannot be
    |                   generated, because generating is what failed.
    | handover_message  the notice that a person has taken the conversation
    |                   over, posted when a manager does so from the panel.
    |
    */

    'failure_message' => env('CHAT_FAILURE_MESSAGE', "Sorry, I couldn't reply just now. Your message is saved for our team - please try again in a moment, or type your email address or phone number and the team will get back to you."),

    'handover_message' => 'A member of our support team has joined this conversation and will reply here shortly.',
];
