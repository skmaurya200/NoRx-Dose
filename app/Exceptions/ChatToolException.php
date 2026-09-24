<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * This shop failed to answer its own function, rather than answering "no".
 *
 * The distinction is the whole reason this class exists. "We do not stock
 * that" and "the catalogue could not be read just now" are different facts,
 * and a visitor told the first when the second happened has been misinformed
 * about what this shop sells.
 */
class ChatToolException extends RuntimeException {}
