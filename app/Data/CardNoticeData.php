<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\NoticeTone;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A notice at the top of a card: what the saved data and the reminders say, with an action when there is one to take.
 */
#[TypeScript]
final class CardNoticeData extends Data
{
    /**
     * @param  string  $key  what tells this notice from the others of the card, for the list of the page (it does not depend on the words)
     * @param  NoticeTone  $tone  information, warning or danger (late)
     * @param  string  $title  the line in bold, empty when the notice is only a text
     * @param  string|null  $text  the text under the title
     * @param  string|null  $action_label  the text of the action, for example «Upload the new one»
     * @param  string|null  $action_href  where the action leads
     */
    public function __construct(
        public readonly string $key,
        public readonly NoticeTone $tone,
        public readonly string $title,
        public readonly ?string $text = null,
        public readonly ?string $action_label = null,
        public readonly ?string $action_href = null,
    ) {}

    /**
     * A warning that is only a text, as the card types write theirs.
     *
     * @param  string  $text  the text
     */
    public static function warning(string $text): self
    {
        return new self('type-note', NoticeTone::Warning, '', $text);
    }
}
