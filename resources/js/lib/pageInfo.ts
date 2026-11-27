/**
 * The explanation of every page, the «i» beside its title (`FmPageInfo`, boards *Posta-info* and *Pannelli*): what the
 * page is for, how to use it, what is good to know and a link to a page nearby. One entry per page, named by the key
 * the page gives to `usePageMeta({ info })`. The lines that belong to a feature the family has off are left out, so
 * with the Budget off no text speaks of money.
 */
import { trans } from 'laravel-vue-i18n';
import type { Features } from '@/lib/features';
import { featureOn } from '@/lib/features';
import { agenda, toSort, upload } from '@/routes';
import { index as banks } from '@/routes/banks';
import { create as invite } from '@/routes/family/invitations';
import { index as receivedMail } from '@/routes/mail';
import { edit as mailSettings } from '@/routes/mail-settings';
import { index as people } from '@/routes/people';
import { edit as areasSettings } from '@/routes/settings/areas';
import { edit as noticesSettings } from '@/routes/settings/notices';

/** The keys of the pages, one per page with a title. */
export const PAGE_INFO_KEYS = [
    'home',
    'search',
    'agenda',
    'areas',
    'area',
    'notices',
    'movements',
    'planned',
    'planned-item',
    'planned-form',
    'movement',
    'comparison',
    'consumption',
    'bank-transactions',
    'card',
    'card-new',
    'card-form',
    'card-payments',
    'card-related',
    'documents',
    'document',
    'document-edit',
    'to-sort',
    'import',
    'upload',
    'gallery',
    'people',
    'organizations',
    'person',
    'person-form',
    'organization',
    'organization-form',
    'invite',
    'invitation-sent',
    'mail',
    'reminder-form',
    'scan',
    'dewarp-check',
    'scanner',
    'linked',
    'settings',
    'settings-account',
    'settings-areas',
    'settings-banks',
    'settings-calendar',
    'settings-categories',
    'settings-expense-categories',
    'settings-family',
    'settings-features',
    'settings-mail',
    'settings-news',
    'settings-notices',
    'settings-support',
] as const;

/** The key of the explanation of a page. */
export type PageInfoKey = (typeof PAGE_INFO_KEYS)[number];

/** A link to a page nearby, at the foot of the explanation or inside a step. */
export type PageInfoLink = {
    /** The text of the link. */
    label: string;
    /** The address, asked when the panel opens. */
    href: () => string;
    /** The feature the page of the link belongs to: with it off the link is left out. */
    feature?: App.Enums.Feature;
    /** The page of the link opens only for an administrator: for the others the link is left out. */
    admin?: boolean;
};

/** Who reads the explanation. */
export type PageInfoReader = {
    /** The account is an administrator of the family; true when it is not known. */
    admin?: boolean;
};

/** A step of *How to use it*. */
export type PageInfoStep = {
    /** The text; `:link` in it is where `link` goes, as a link in the sentence. */
    text: string;
    /** The link inside the text. */
    link?: PageInfoLink;
    /** The name of a prop of the page whose text is shown under the step, with the button that copies it. */
    copy?: string;
};

/** The explanation of a page, with the lines of the features that are off already left out. */
export type PageInfo = {
    /** The name of the page, the title of the panel. */
    title: string;
    /** *What it is for*: a sentence or two. */
    purpose: string;
    /** *How to use it*: the steps, numbered. */
    steps: PageInfoStep[];
    /** *Good to know*: the notes, if any. */
    notes?: string[];
    /** The link at the foot of the panel, if any. */
    link?: PageInfoLink;
    /** The feature the page belongs to: with it off the page does not open. */
    feature?: App.Enums.Feature;
};

/** A line of an entry, with the feature it needs (or the one it is written for when that is off). */
type Line = PageInfoStep & {
    /** Shown only while this feature is on. */
    feature?: App.Enums.Feature;
    /** Shown only while this feature is off: the same sentence without what that feature adds. */
    without?: App.Enums.Feature;
};

/** A line of an entry: a text for everyone, or one that depends on a feature. */
type Text = string | Line;

/** An entry as written below, before the lines of the features that are off are left out. */
type Entry = {
    /** The feature the page belongs to. */
    feature?: App.Enums.Feature;
    /** The name of the page. */
    title: string;
    /** The sentences of *What it is for*, joined with a space. */
    purpose: Text[];
    /** The steps. */
    steps: Text[];
    /** The notes. */
    notes?: Text[];
    /** The link at the foot. */
    link?: PageInfoLink;
};

/**
 * A line shown only while a feature is on.
 *
 * @param feature - the feature the line needs
 * @param text - the line
 * @param extra - the link or the text to copy of a step
 * @returns the line
 */
function only(
    feature: App.Enums.Feature,
    text: string,
    extra: Omit<PageInfoStep, 'text'> = {},
): Line {
    return { text, feature, ...extra };
}

/**
 * A line shown only while a feature is off, in place of the one that speaks of it.
 *
 * @param feature - the feature that is off
 * @param text - the line
 * @returns the line
 */
function unless(feature: App.Enums.Feature, text: string): Line {
    return { text, without: feature };
}

/** The entries, one function per page: the texts are translated when the panel opens. */
const ENTRIES: Record<PageInfoKey, () => Entry> = {
    home: () => ({
        title: trans('Home'),
        purpose: [
            only(
                'budget',
                trans(
                    'Your day at a glance: what to pay soon, the deadlines coming up and the documents still to complete.',
                ),
            ),
            unless(
                'budget',
                trans(
                    'Your day at a glance: the deadlines coming up and the documents still to complete.',
                ),
            ),
        ],
        steps: [
            trans(
                'When the box of the documents to complete shows up, tap it: the files wait in To sort.',
            ),
            only(
                'budget',
                trans(
                    'The blue box says what to pay in the next 30 days: tap a row to open it.',
                ),
            ),
            trans(
                'Below you find the deadlines coming up: tap a row to open it.',
            ),
            trans(
                'While To begin is there, follow its steps to set up the app.',
            ),
        ],
        notes: [
            trans('Hide takes To begin away for you.'),
            only(
                'mail',
                trans(
                    'The last mails with files show here too, under Mail that arrived.',
                ),
            ),
        ],
        link: { label: trans('Open the Agenda'), href: () => agenda().url },
    }),
    search: () => ({
        title: trans('Search'),
        purpose: [
            trans(
                'One box to find anything in the app: cards, documents, people and organizations.',
            ),
        ],
        steps: [
            trans(
                'Write what you are looking for: a name, a plate, a word of a document.',
            ),
            trans('The chips on top show one group of results at a time.'),
            trans('Tap a result to open it.'),
        ],
        notes: [
            trans(
                'If nothing turns up, try a shorter word or check how it is written.',
            ),
        ],
    }),
    agenda: () => ({
        title: trans('Agenda'),
        purpose: [
            only(
                'budget',
                trans(
                    'Everything to do in one list: the deadlines of the cards, the planned expenses to pay and the tasks you write.',
                ),
            ),
            unless(
                'budget',
                trans(
                    'Everything to do in one list: the deadlines of the cards and the tasks you write.',
                ),
            ),
        ],
        steps: [
            trans(
                'To do shows what is coming, the late things first; Done shows what was closed.',
            ),
            trans('The filters narrow the list by period, person and area.'),
            trans(
                'Tap a row to open it: Done closes it, Postpone moves it to another day.',
            ),
            only(
                'budget',
                trans('For a payment, I paid it records it in the Budget.'),
            ),
            trans('New task adds something to do, written by you.'),
        ],
        notes: [
            trans(
                'The icons beside New task switch between the list and the calendar of the month.',
            ),
            trans(
                'Connect a calendar puts everything in the calendar of your phone, and it updates by itself.',
            ),
        ],
    }),
    areas: () => ({
        title: trans('Areas'),
        purpose: [
            trans(
                'The parts of the life of the family (home, vehicles, insurance…), each with its cards.',
            ),
        ],
        steps: [
            trans('Tap an area to see all its cards.'),
            trans('A red number tells how many cards need you now.'),
            trans('New card adds one: choose the type and fill it in.'),
        ],
        notes: [
            trans(
                'The order of the areas, and the ones the family does not use, are chosen in Settings › Areas.',
            ),
            trans('The search still finds the cards of a hidden area.'),
        ],
        link: {
            label: trans('Order of the areas'),
            href: () => areasSettings().url,
        },
    }),
    area: () => ({
        title: trans('An area'),
        purpose: [
            trans('All the cards of this area, with how many need you now.'),
        ],
        steps: [
            trans('Tap a card to open it.'),
            trans('New adds a card of this area.'),
            trans('The cards closed stay folded under Closed.'),
        ],
        notes: [
            trans(
                'If the area is hidden in the settings, the page still opens and says so.',
            ),
        ],
    }),
    notices: () => ({
        title: trans('Notices'),
        purpose: [
            trans('All the notices the app sent you, the new ones first.'),
        ],
        steps: [
            trans('Tap a notice to open what it is about.'),
            trans('Mark all as read once you have seen them.'),
            trans('The search finds an old notice by its words.'),
        ],
        notes: [
            trans(
                'Which notices arrive, when and to whom is chosen in Settings › Notices.',
            ),
        ],
        link: {
            label: trans('Settings of the notices'),
            href: () => noticesSettings().url,
        },
    }),
    movements: () => ({
        feature: 'budget',
        title: trans('Movements'),
        purpose: [
            trans(
                'The expenses and incomes of the month, with what came in, what went out and what is left.',
            ),
        ],
        steps: [
            trans(
                'Move between the months, or search a title, an amount or a day to look in every month.',
            ),
            trans(
                'The filters narrow the list by kind, account, amount and days.',
            ),
            trans(
                'New expense or income adds one; a row opens it to change it.',
            ),
        ],
        notes: [trans('A planned expense you mark as paid shows up here too.')],
    }),
    planned: () => ({
        feature: 'budget',
        title: trans('Planned expenses'),
        purpose: [
            trans(
                'The expenses and incomes that come back, such as rent, bills and salary, with what they come to in a month.',
            ),
        ],
        steps: [
            trans(
                'The rows go by next due date: tap one to see its due dates and payments.',
            ),
            trans('Add creates a new planned expense or income.'),
            trans(
                'The search and the filters find one by title, amount or due date.',
            ),
        ],
        notes: [
            trans('The ones that ended stay folded at the bottom.'),
            only(
                'bank_feed',
                trans(
                    'With a linked bank, the rule How it shows in the bank pays the due date by itself.',
                ),
            ),
        ],
    }),
    'planned-item': () => ({
        feature: 'budget',
        title: trans('Planned expense'),
        purpose: [
            trans(
                'One planned expense or income: its next due dates, its exceptions and the payments year by year.',
            ),
        ],
        steps: [
            trans(
                'Tap an open due date to change it, or to say that you paid it.',
            ),
            trans('A paid due date leads to its movement.'),
            trans(
                'An exception changes one due date only: Add writes a new one.',
            ),
        ],
        notes: [
            trans(
                'Edit changes the planned expense for all the due dates to come.',
            ),
        ],
    }),
    'planned-form': () => ({
        feature: 'budget',
        title: trans('Planned expense or income'),
        purpose: [
            trans(
                'An expense or income that comes back: what it is, how much, when and how often.',
            ),
        ],
        steps: [
            trans('Write what it is, the amount and the first due date.'),
            trans('Choose how often it comes back and, if it ends, when.'),
            trans(
                'Choose the category and, for an expense, the card it belongs to.',
            ),
            trans(
                'Save: the due dates show up in the Agenda and in the Budget.',
            ),
        ],
        notes: [
            trans(
                'Started from a card, the form comes with the category and the holder already filled in.',
            ),
            only(
                'bank_feed',
                trans(
                    'How it shows in the bank lets the app recognise the payment and record it by itself.',
                ),
            ),
        ],
    }),
    movement: () => ({
        feature: 'budget',
        title: trans('Expense or income'),
        purpose: [
            trans(
                'One expense or income: what it is, the amount, the day and the documents that go with it.',
            ),
        ],
        steps: [
            trans('Write the title, the amount and the day.'),
            trans(
                'Choose the category, the account and, if it fits, the card.',
            ),
            trans('Add the receipt or the bill in the box of the documents.'),
            trans('Save.'),
        ],
        notes: [
            trans(
                'Started from a planned expense or from a card, the form comes already filled in.',
            ),
        ],
    }),
    comparison: () => ({
        feature: 'budget',
        title: trans('Planned and spent'),
        purpose: [
            trans(
                'The expenses of a month against what you planned, category by category.',
            ),
        ],
        steps: [
            trans(
                'Each bar is what was spent; the mark is where the plan reaches.',
            ),
            trans('The chart shows the last six months.'),
            trans('The arrows change the month.'),
        ],
        notes: [
            trans(
                'On a wide screen you also see the due dates of the month nobody registered yet.',
            ),
        ],
    }),
    consumption: () => ({
        feature: 'consumption',
        title: trans('Usage'),
        purpose: [
            trans(
                'How much electricity, gas or water the house uses, month by month, against the year before.',
            ),
        ],
        steps: [
            trans('Choose the type and the utility on top.'),
            trans(
                'Tap a bar to see the month: what it used, what it cost and its bills.',
            ),
            trans('Spending turns the chart into euros.'),
            trans(
                'Read the bills fills the chart from the bills you already keep.',
            ),
        ],
        notes: [
            trans(
                'A consumption without a bill is written by hand with Add a consumption.',
            ),
        ],
    }),
    'bank-transactions': () => ({
        feature: 'bank_feed',
        title: trans('From the bank'),
        purpose: [
            trans(
                'The movements your bank sent that no planned expense recognised yet.',
            ),
        ],
        steps: [
            trans(
                'Create the expense (or the income) makes one from the movement, already filled in.',
            ),
            trans('It belongs to a planned expense pays its closest due date.'),
            trans(
                'Ignore hides a movement nobody has to deal with: you find it under All.',
            ),
            trans('Update reads the banks now.'),
        ],
        notes: [
            trans('The banks are also read by themselves, three times a day.'),
            trans(
                'Ignored movements stay here for 12 months, then they are removed.',
            ),
        ],
        link: { label: trans('Linked banks'), href: () => banks().url },
    }),
    card: () => ({
        title: trans('A card'),
        purpose: [
            only(
                'budget',
                trans(
                    'Everything about one thing of the family: its data, its documents, its deadlines and what it costs.',
                ),
            ),
            unless(
                'budget',
                trans(
                    'Everything about one thing of the family: its data, its documents and its deadlines.',
                ),
            ),
        ],
        steps: [
            trans(
                'The notices on top say what needs you: a deadline near, a document missing.',
            ),
            trans(
                'Upload adds a document; Link attaches one you already have.',
            ),
            trans('Edit changes the data of the card.'),
            only(
                'budget',
                trans(
                    'Under the documents you find its planned expenses and payments.',
                ),
            ),
            only(
                'budget',
                trans(
                    'On a vehicle, Deadlines lines up what is coming by date, with the amount: road tax, inspection, insurance and the other planned expenses.',
                ),
            ),
            unless(
                'budget',
                trans(
                    'On a vehicle, Deadlines lines up by date the road tax, the inspection and the expiry of the insurance.',
                ),
            ),
            only(
                'budget',
                trans(
                    'Add the expense, beside a date, creates the planned expense that pays it, already filled in.',
                ),
            ),
            trans(
                'Add the date, at the bottom, takes you to the form to write a date the vehicle is missing.',
            ),
        ],
        notes: [
            trans(
                'The dates of the card become deadlines in the Agenda and in the notices, by themselves.',
            ),
            trans(
                'A vehicle expects its registration document: it counts when it is in the category Vehicle registration document, or in one under it, even if you rename it.',
            ),
            trans(
                'The + beside the linked cards adds a new card already linked.',
            ),
        ],
    }),
    'card-new': () => ({
        title: trans('New card'),
        purpose: [
            trans(
                'Choose what kind of card to add: each type says what it is for.',
            ),
        ],
        steps: [
            trans('Find the type in its area and tap it.'),
            trans('Fill in the form and save.'),
        ],
        notes: [
            trans(
                'Have a document? Upload it: the app reads it and proposes the card.',
            ),
        ],
        link: { label: trans('Upload a document'), href: () => upload().url },
    }),
    'card-form': () => ({
        title: trans('The card'),
        purpose: [
            trans(
                'The data of a card: what you write here shows on the card, and its dates become deadlines.',
            ),
        ],
        steps: [
            trans('Fill in the fields you know: the rest can wait.'),
            trans(
                'On the side, add the documents of the card or link ones you already have.',
            ),
            trans('Save.'),
        ],
        notes: [
            trans(
                'The dates you write, such as an expiry, show up in the Agenda.',
            ),
            only(
                'budget',
                trans(
                    'Some cards, such as a subscription, also create their planned expense.',
                ),
            ),
            trans(
                'For utilities, jobs and enrollments the title makes itself from the fields you choose: at the top you see how it will be.',
            ),
            trans(
                'The + next to the organization creates a new one with name and type, and chooses it right away. You add the rest from the organization page.',
            ),
        ],
    }),
    'card-payments': () => ({
        feature: 'budget',
        title: trans('Payments'),
        purpose: [trans('What this card paid, or received, year by year.')],
        steps: [
            trans('On top, the total of this year and the average payment.'),
            trans('Record payment adds one.'),
            trans('Tap a payment to open it.'),
        ],
        notes: [
            trans(
                'Only the payments of the planned expenses of the card count here.',
            ),
        ],
    }),
    'card-related': () => ({
        title: trans('Links'),
        purpose: [trans('All the cards that go with this one, in one list.')],
        steps: [
            trans('Tap a card to open it.'),
            trans('Add creates a new card already linked to this one.'),
        ],
        notes: [
            trans(
                'The cards with a date coming are first. The closed ones are at the bottom.',
            ),
        ],
    }),
    documents: () => ({
        title: trans('Documents'),
        purpose: [trans('All the documents the family keeps, in one place.')],
        steps: [
            trans('Search by title, or narrow the list with the filters.'),
            trans(
                'Tap a document to see it: on a wide screen it opens beside the list.',
            ),
            trans('Upload adds new ones.'),
        ],
        notes: [
            trans(
                'A document can belong to more cards, people or organizations at once.',
            ),
        ],
        link: { label: trans('Upload a document'), href: () => upload().url },
    }),
    document: () => ({
        title: trans('A document'),
        purpose: [trans('One document: the file and what we know about it.')],
        steps: [
            trans('Download saves the file on your device.'),
            trans(
                'Edit changes the title, the date, the category and what it belongs to.',
            ),
        ],
        notes: [trans('Delete asks first, and only an administrator can.')],
    }),
    'document-edit': () => ({
        title: trans('Edit the document'),
        purpose: [
            trans(
                'The data of a document, with the file beside you to read from.',
            ),
        ],
        steps: [
            trans('Change what is wrong in the form.'),
            trans(
                'Fill in from the file reads the file again and writes only into the empty fields.',
            ),
            trans('Save.'),
        ],
        notes: [
            trans(
                'Fill in from the file is not there when the reading is off or the readings of the month are used up.',
            ),
        ],
    }),
    'to-sort': () => ({
        title: trans('To sort'),
        purpose: [
            only(
                'mail',
                trans(
                    'The files waiting to become documents, whoever sent them: uploaded here, from the phone or by mail.',
                ),
            ),
            unless(
                'mail',
                trans(
                    'The files waiting to become documents, whoever sent them: uploaded here or from the phone.',
                ),
            ),
        ],
        steps: [
            trans('Add files with Choose a file or Take a photo.'),
            trans(
                'Choose the files and press Check: the app reads them and proposes what they are.',
            ),
            trans(
                'In Import more files you check each one and save it as a document.',
            ),
        ],
        notes: [trans('The files stay here until you import or delete them.')],
        link: {
            label: trans('Received mail'),
            href: () => receivedMail().url,
            feature: 'mail',
        },
    }),
    import: () => ({
        title: trans('Import more files'),
        purpose: [
            trans(
                'The files are checked one by one and saved as documents, all together.',
            ),
        ],
        steps: [
            trans(
                'Look at the file and fix in the form what the reading got wrong.',
            ),
            only(
                'budget',
                trans(
                    'Under What I do too, choose what to create with it: the expense or income, the cards, the health event.',
                ),
            ),
            unless(
                'budget',
                trans(
                    'Under What I do too, choose what to create with it: the cards, the health event.',
                ),
            ),
            trans(
                'Next goes to the next file; on the last one the button imports them all.',
            ),
        ],
        notes: [
            trans('Do not import this file leaves it in To sort.'),
            trans('A file still being read is waited for.'),
        ],
    }),
    upload: () => ({
        title: trans('Upload a document'),
        purpose: [trans('Bring files into the app: PDFs and photos.')],
        steps: [
            trans('Choose the files, or drag them here.'),
            trans('On the phone you can take a photo of a sheet instead.'),
            trans(
                'The app reads them and opens Import more files, where you check and save them.',
            ),
        ],
        notes: [trans('PDF, JPG, PNG and HEIC, up to 25 MB each.')],
    }),
    gallery: () => ({
        feature: 'gallery',
        title: trans('Drawings and crafts'),
        purpose: [
            trans(
                'The drawings and crafts of the children, kept as pictures, year by year.',
            ),
        ],
        steps: [
            trans('Add takes a photo or a PDF of a drawing.'),
            trans(
                'Tap a picture to see it big; the heart puts it among the Favourites.',
            ),
            trans(
                'The chips show only the favourites, or the drawings of one child.',
            ),
        ],
    }),
    people: () => ({
        title: trans('People'),
        purpose: [
            trans(
                'The people of the family, with who has an account to use the app.',
            ),
        ],
        steps: [
            trans('Tap a person to see their things.'),
            trans(
                'New person adds someone who lives with you; Invite a family member lets them in with their own account.',
            ),
            trans(
                'Organizations, beside People, lists the organizations the family deals with.',
            ),
        ],
        link: {
            label: trans('Invite a family member'),
            href: () => invite().url,
            admin: true,
        },
    }),
    organizations: () => ({
        title: trans('Organizations'),
        purpose: [
            trans(
                'The organizations the family deals with: insurance companies, schools, doctors, utilities…',
            ),
        ],
        steps: [
            trans('Tap one to see its contacts and documents.'),
            trans('New organization adds one, with its contacts.'),
        ],
        link: { label: trans('People'), href: () => people().url },
    }),
    person: () => ({
        title: trans('A person'),
        purpose: [
            trans(
                'One person of the family: what they have to do, their documents and the cards that name them.',
            ),
        ],
        steps: [
            trans(
                'To do shows their open tasks; the link opens the Agenda on them.',
            ),
            trans(
                'Their documents are on the side, with the link to all of them.',
            ),
            trans('Edit changes their data.'),
        ],
        notes: [trans('A person with an account cannot be deleted from here.')],
    }),
    'person-form': () => ({
        title: trans('The person'),
        purpose: [
            trans(
                'The data of a person: name, who they are, birth date, tax code and notes.',
            ),
        ],
        steps: [
            trans('Fill in what you know.'),
            trans('Add a photo beside the form, if you like.'),
            trans('Save.'),
        ],
        notes: [
            trans('A new person has no account: to let them in, invite them.'),
        ],
        link: {
            label: trans('Invite a family member'),
            href: () => invite().url,
            admin: true,
        },
    }),
    organization: () => ({
        title: trans('An organization'),
        purpose: [
            trans(
                'An organization the family deals with: its contacts, its documents and the cards that name it.',
            ),
        ],
        steps: [
            trans(
                'On the side: phone, website, email and the notes, such as the customer code.',
            ),
            trans('Its documents are here, with the link to all of them.'),
            trans('Edit changes its data.'),
        ],
        notes: [trans('Deleting it keeps its documents and cards.')],
    }),
    'organization-form': () => ({
        title: trans('The organization'),
        purpose: [
            trans(
                'The data of an organization: name, types, contacts and notes.',
            ),
        ],
        steps: [
            trans(
                'Write the name and choose its types: it can have more than one.',
            ),
            trans(
                'Add phone, website, email and the notes, such as the customer code.',
            ),
            trans('Add the logo beside the form, if you like.'),
        ],
    }),
    invite: () => ({
        title: trans('Invite a family member'),
        purpose: [
            trans(
                'Someone of the family starts using the app, with their own account.',
            ),
        ],
        steps: [
            trans('Write their email.'),
            trans(
                'Choose who they are: a person already in the family, or a new one.',
            ),
            trans(
                'Choose what they can do: everything, or see everything and upload documents.',
            ),
            trans('Send: they get an email with the link to come in.'),
        ],
    }),
    'invitation-sent': () => ({
        title: trans('Invitation sent'),
        purpose: [trans('The invitation has left by email.')],
        steps: [
            trans(
                'They open the email and choose their password: that is all.',
            ),
            trans(
                'If the email does not arrive, copy the link and send it another way.',
            ),
        ],
    }),
    mail: () => ({
        feature: 'mail',
        title: trans('Received mail'),
        purpose: [
            only(
                'budget',
                trans(
                    'The family has an email address of its own. The bills and documents you send to it arrive here, and we read them for you: amounts, due dates and who sent them.',
                ),
            ),
            unless(
                'budget',
                trans(
                    'The family has an email address of its own. The bills and documents you send to it arrive here, and we read them for you: due dates and who sent them.',
                ),
            ),
        ],
        steps: [
            { text: trans('Copy the address of the family.'), copy: 'address' },
            trans(
                'Forward a mail with the bill, or set up automatic forwarding from Gmail: then they arrive by themselves.',
            ),
            {
                text: trans(
                    'You find the document in :link, already read: check it and save it.',
                ),
                link: { label: trans('To sort'), href: () => toSort().url },
            },
        ],
        notes: [
            trans('We keep only PDF attachments and photos.'),
            trans('Mails without attachments stay here, to read.'),
            trans('The mail is turned on and off in Settings › What you use.'),
        ],
        link: {
            label: trans('Settings of the address'),
            href: () => mailSettings().url,
        },
    }),
    'reminder-form': () => ({
        title: trans('Task'),
        purpose: [
            trans(
                'Something to do that you write by hand: what, when and who takes care of it.',
            ),
        ],
        steps: [
            trans('Write what to do and choose the day.'),
            trans(
                'Choose who takes care of it and, if it fits, the card, document or person it is about.',
            ),
            trans('Choose when the notices arrive and to whom.'),
            trans('Save: it shows up in the Agenda.'),
        ],
    }),
    scan: () => ({
        title: trans('New document'),
        purpose: [
            trans(
                'Take photos of the pages of a document and send them to To sort.',
            ),
        ],
        steps: [
            trans(
                'Take a photo of each page: the app finds the edges of the sheet by itself.',
            ),
            trans(
                'Crop fixes the edges; Improve makes the sheet easier to read.',
            ),
            trans('Upload sends all the pages as one document.'),
        ],
        notes: [trans('Before leaving pages not uploaded, the app asks.')],
    }),
    'dewarp-check': () => ({
        title: trans('Straighten check'),
        purpose: [
            trans(
                'A page for the development machine: it times how long a photo takes to straighten.',
            ),
        ],
        steps: [
            trans('Choose a photo of a sheet.'),
            trans('Wait for the result and the times.'),
        ],
        notes: [trans('Nothing is saved or sent.')],
    }),
    scanner: () => ({
        title: trans('Scan the sheets'),
        purpose: [
            trans(
                'Paper documents through the scanner of the house, driven from this computer.',
            ),
        ],
        steps: [
            trans(
                'Put the sheets in the feeder and press Start scanning: front and back go through, and the blank sides are put aside.',
            ),
            trans('Check each page: rotate it, remove it or improve it.'),
            trans('Upload makes one PDF and sends it to To sort.'),
        ],
        notes: [
            trans(
                'The scanner works from a Mac or a Windows PC, with the program of the scanner open.',
            ),
        ],
    }),
    linked: () => ({
        title: trans('Phone linked'),
        purpose: [
            trans(
                'This phone is linked to your account, to take photos of documents.',
            ),
        ],
        steps: [
            trans('Put the app on the Home screen: it opens with one tap.'),
            trans('Take a photo opens the camera: the pages go to To sort.'),
        ],
        notes: [
            trans(
                'From this phone you only take photos; to see everything, sign in with the password.',
            ),
        ],
    }),
    settings: () => ({
        title: trans('Settings'),
        purpose: [
            trans(
                'The settings of the app: yours, and the ones of the whole family.',
            ),
        ],
        steps: [
            trans('Tap a section to open it.'),
            trans(
                'My account is only yours; the rest is the same for the whole family.',
            ),
        ],
        notes: [
            trans(
                'Only an administrator changes the settings of the family; the others see them.',
            ),
        ],
    }),
    'settings-account': () => ({
        title: trans('My account'),
        purpose: [trans('Your account: name, email, photo and password.')],
        steps: [
            trans('Change your data and save.'),
            trans('Change the password whenever you want.'),
            trans('Log out leaves the app on this device.'),
        ],
        notes: [
            trans(
                'The only administrator of the family cannot delete their account.',
            ),
        ],
    }),
    'settings-areas': () => ({
        title: trans('Areas'),
        purpose: [
            trans(
                'The order of the areas, and the ones the family does not use.',
            ),
        ],
        steps: [
            trans('Drag an area by its handle to move it.'),
            trans('Turn off Show to hide an area.'),
            trans('Each change is saved at once.'),
        ],
        notes: [
            trans('Back to the default order puts everything back.'),
            trans(
                'The cards of a hidden area stay, and the search still finds them.',
            ),
        ],
    }),
    'settings-banks': () => ({
        feature: 'bank_feed',
        title: trans('Linked banks'),
        purpose: [
            trans(
                'The banks linked to the family: their movements arrive in the Budget by themselves.',
            ),
        ],
        steps: [
            trans(
                'Connect a bank: choose it and sign in on the website of the bank.',
            ),
            trans('Each bank says how it is doing and when it was read last.'),
            trans('Reconnect a bank when its permission has ended.'),
        ],
        notes: [
            trans(
                'The bank asks to renew the permission every few months: the app tells you before.',
            ),
            trans('Of the IBAN we keep only the last 4 digits.'),
        ],
    }),
    'settings-calendar': () => ({
        title: trans('Calendar'),
        purpose: [
            trans(
                'The calendar of the family, to see deadlines and tasks in Google Calendar or on the phone.',
            ),
        ],
        steps: [
            trans('Copy the address.'),
            trans(
                'Add it to your calendar app, as a calendar to subscribe to.',
            ),
        ],
        notes: [
            trans(
                'An administrator can change the secret address: the old one stops working.',
            ),
        ],
    }),
    'settings-categories': () => ({
        title: trans('Document categories'),
        purpose: [trans('The categories that keep the documents in order.')],
        steps: [
            trans('Tap a category to rename it or move it.'),
            trans('New category adds one.'),
        ],
        notes: [
            trans(
                'A category with documents or subcategories cannot be deleted.',
            ),
        ],
    }),
    'settings-expense-categories': () => ({
        feature: 'budget',
        title: trans('Expense categories'),
        purpose: [trans('Each kind of expense, with the card it belongs to.')],
        steps: [
            trans('Tap a main category and choose its Linked card.'),
            trans('The subcategories follow their main category.'),
        ],
    }),
    'settings-family': () => ({
        title: trans('Family name'),
        purpose: [
            trans(
                'The name of the family, seen in the menu, in the emails and in the calendar.',
            ),
        ],
        steps: [trans('Change the name and save.')],
        notes: [
            only('mail', trans('The address for the bills stays the same.')),
        ],
    }),
    'settings-features': () => ({
        title: trans('What you use'),
        purpose: [trans('The parts of the app the family turns on or off.')],
        steps: [
            trans('Read under each switch what changes.'),
            trans('Each switch saves at once.'),
        ],
        notes: [trans('Only an administrator changes them.')],
    }),
    'settings-mail': () => ({
        feature: 'mail',
        title: trans('Address for the bills'),
        purpose: [trans('The address the family forwards its bills to.')],
        steps: [
            trans('Copy the address.'),
            trans('Follow the three steps to forward from Gmail.'),
            trans('Choose who gets a notice when a file arrives, and save.'),
        ],
        link: { label: trans('Received mail'), href: () => receivedMail().url },
    }),
    'settings-news': () => ({
        title: trans("What's new"),
        purpose: [trans('What changes in the app, version by version.')],
        steps: [trans('Read from the newest: the version you have is marked.')],
    }),
    'settings-notices': () => ({
        title: trans('Notices'),
        purpose: [trans('Which notices the app sends, when and to whom.')],
        steps: [
            trans('Each card is a group of deadlines, with its notices.'),
            trans(
                'Edit changes how many days before, how (app or email) and to whom.',
            ),
            trans('Save.'),
        ],
    }),
    'settings-support': () => ({
        title: trans('Support'),
        purpose: [trans('Support sees the app as you do, to help you.')],
        steps: [
            trans('Turn on the permission: it lasts 24 hours.'),
            trans('Turn it off whenever you want.'),
        ],
        notes: [
            trans('Only an administrator gives or takes back the permission.'),
        ],
    }),
};

/**
 * Whether a line is kept with these features.
 *
 * @param line - the line
 * @param features - the features of the family, null for everything on
 * @param admin - the reader is an administrator
 * @returns true when the line shows
 */
function kept(line: Text, features: Features | null, admin: boolean): boolean {
    if (typeof line === 'string') {
        return true;
    }

    // A step whose link goes to a page that is off, or closed to the reader, goes too: its sentence would point
    // nowhere
    return (
        featureOn(features, line.feature) &&
        (line.link === undefined ||
            linkOn(line.link, features, admin) !== undefined) &&
        (line.without === undefined || !featureOn(features, line.without))
    );
}

/**
 * The step of a line, without the features it needs.
 *
 * @param line - the line
 * @returns the step
 */
function step(line: Text): PageInfoStep {
    if (typeof line === 'string') {
        return { text: line };
    }

    const { feature: _feature, without: _without, ...rest } = line;

    return rest;
}

/**
 * The link, when its feature is on and the reader can open its page.
 *
 * @param link - the link, if any
 * @param features - the features of the family
 * @param admin - the reader is an administrator
 * @returns the link, or undefined
 */
function linkOn(
    link: PageInfoLink | undefined,
    features: Features | null,
    admin: boolean,
): PageInfoLink | undefined {
    return link &&
        featureOn(features, link.feature) &&
        (admin || link.admin !== true)
        ? link
        : undefined;
}

/**
 * The explanation of a page, translated, with the lines of the features that are off left out.
 *
 * @param key - the key of the page
 * @param features - the features of the family; null counts as everything on
 * @param reader - who reads it: the links to pages only an administrator opens are left out for the others
 * @returns the explanation
 */
export function pageInfo(
    key: PageInfoKey,
    features: Features | null,
    { admin = true }: PageInfoReader = {},
): PageInfo {
    const entry = ENTRIES[key]();
    const keep = (lines: Text[]): Text[] =>
        lines.filter((line) => kept(line, features, admin));
    const notes = keep(entry.notes ?? []).map((line) => step(line).text);
    const link = linkOn(entry.link, features, admin);

    return {
        title: entry.title,
        purpose: keep(entry.purpose)
            .map((line) => step(line).text)
            .join(' '),
        steps: keep(entry.steps).map(step),
        ...(notes.length > 0 ? { notes } : {}),
        ...(link ? { link } : {}),
        ...(entry.feature ? { feature: entry.feature } : {}),
    };
}
