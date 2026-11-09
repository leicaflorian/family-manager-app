<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reminders\SaveReminderGroups;
use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Data\NoticeSettingsData;
use App\Data\OptionData;
use App\Data\ReminderGroupData;
use App\Data\ReminderPersonData;
use App\Http\Requests\Reminders\UpdateReminderGroupsRequest;
use App\Models\Person;
use App\Models\ReminderGroup;
use App\Models\User;
use App\Services\Reminders\GroupExamples;
use App\Support\InstallationSettings;
use App\Support\Reminders\GroupRules;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Settings → Notices*: the groups of notices of the family. Everyone sees them; only an administrator saves them (the
 * request asks the policy).
 */
final class NoticeSettingsController extends Controller
{
    /**
     * The page.
     *
     * @param  User  $user  whoever signed in
     * @param  GroupExamples  $examples  the sentences under the groups
     * @param  InstallationSettings  $installation  the time of the morning round
     */
    public function edit(#[CurrentUser] User $user, GroupExamples $examples, InstallationSettings $installation): Response
    {
        $today = CarbonImmutable::today('Europe/Rome');
        $people = Person::query()->with(['photo', 'user'])->orderBy('first_name')->orderBy('last_name')->get();

        $with = $people->filter(static fn (Person $person): bool => $person->user_id !== null);
        $without = $people->filter(static fn (Person $person): bool => $person->user_id === null);

        return Inertia::render('settings/Notices', ['settings' => new NoticeSettingsData(
            groups: array_values(ReminderGroup::query()->orderBy('id')->get()
                ->map(static fn (ReminderGroup $group): ReminderGroupData => ReminderGroupData::fromGroup($group, $examples->for($group, $today)))
                ->all()),
            kinds: array_map(static fn (DeadlineSource $source): OptionData => new OptionData($source->field, GroupRules::typeName($source->field)), DeadlineSources::all()),
            people: array_values($with->map(static fn (Person $person): ReminderPersonData => new ReminderPersonData($person->id, $person->first_name, $person->initials, $person->photoUrl()))->all()),
            without_account: array_values($without->map(static fn (Person $person): string => $person->first_name)->all()),
            reminders_time: $installation->remindersTime(),
            can_change: $user->can('create', ReminderGroup::class),
        )]);
    }

    /**
     * Saves the whole list of groups.
     *
     * @param  UpdateReminderGroupsRequest  $request  the groups, checked
     * @param  SaveReminderGroups  $save  the action
     */
    public function update(UpdateReminderGroupsRequest $request, SaveReminderGroups $save): RedirectResponse
    {
        $save->handle($request->groups());

        return to_route('settings.notices.edit');
    }
}
