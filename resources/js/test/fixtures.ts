/**
 * Fake data for the tests of the pages, the data more than one file needs.
 */
import type { MovementFormOptions } from '@/lib/budget';

/** My account: Marco Bianchi, the only administrator. */
export const account: App.Data.AccountData = {
    first_name: 'Marco',
    last_name: 'Bianchi',
    email: 'marco@example.com',
    role: 'Administrator',
    is_admin: true,
    initials: 'MB',
    photo: null,
    is_last_admin: true,
    family: 'Bianchi',
};

/** A document of the Bianchis, as *A document* receives it. */
export const documentDetail: App.Data.DocumentDetailData = {
    id: 5,
    title: 'Busta paga di ottobre',
    category_id: 4,
    category: 'Lavoro › Buste paga',
    person_id: 7,
    person: 'Laura Bianchi',
    person_initials: 'LB',
    person_photo: null,
    organization_id: 9,
    organization: 'Comune di Bologna',
    date: '2026-10-23',
    expiry_date: null,
    status: 'active',
    status_label: 'Active',
    notes: 'With the arrears of the contract.',
    tags: ['salary', '2026'],
    file: {
        name: 'Busta paga ottobre 2026.pdf',
        mime_type: 'application/pdf',
        size: 318000,
        url: '/files/12',
        download_url: '/files/12/download',
        preview_url: '/files/12/preview',
    },
    links: [
        {
            type: 'employment',
            id: 3,
            type_label: 'Employments',
            label: 'Laura - Comune di Bologna',
            href: '/cards/employment/3',
        },
    ],
    can_edit: true,
    can_delete: true,
};

/**
 * A category of the tree, with what the menus read.
 *
 * @param id - its id
 * @param name - its name
 * @param parentId - the id of its main category, null for a main one
 * @param children - its subcategories
 * @returns the category
 */
function category(
    id: number,
    name: string,
    parentId: number | null,
    children: App.Data.CategoryData[] = [],
): App.Data.CategoryData {
    return {
        id,
        name,
        parent_id: parentId,
        icon: null,
        color: null,
        documents_count: 0,
        children,
    };
}

/** The choices of the filters of *Documents*. */
export const documentFilterOptions = {
    categories: [
        category(1, 'Casa e utenze', null, [
            category(2, 'Luce', 1),
            category(5, 'Altro', 1),
        ]),
        category(3, 'Lavoro', null, [
            category(4, 'Buste paga', 3),
            category(6, 'Altro', 3),
        ]),
    ],
    people: [{ value: '7', label: 'Laura Bianchi', help: null }],
    organizations: [{ value: '9', label: 'Comune di Bologna', help: null }],
    statuses: [
        { value: 'active', label: 'Active', help: null },
        { value: 'archived', label: 'Archived', help: null },
        { value: 'expired', label: 'Expired', help: null },
    ],
};

/** The Bianchis, as at the top of *People* and *Organizations*. */
export const familySummary: App.Data.FamilySummaryData = {
    name: 'Bianchi',
    people: 3,
    organizations: 14,
};

/** The three Bianchis of *People*: Marco is whoever is looking. */
export const peopleList: App.Data.PersonData[] = [
    {
        id: 1,
        first_name: 'Marco',
        last_name: 'Bianchi',
        initials: 'MB',
        photo: null,
        role_label: 'Parent',
        age: 44,
        account_label: 'Administrator',
        is_me: true,
        documents_count: 8,
        records_count: 11,
    },
    {
        id: 2,
        first_name: 'Laura',
        last_name: 'Bianchi',
        initials: 'LB',
        photo: null,
        role_label: 'Parent',
        age: 40,
        account_label: 'Administrator',
        is_me: false,
        documents_count: 5,
        records_count: 6,
    },
    {
        id: 3,
        first_name: 'Giulia',
        last_name: 'Bianchi',
        initials: 'GB',
        photo: null,
        role_label: 'Child',
        age: 10,
        account_label: null,
        is_me: false,
        documents_count: 4,
        records_count: 2,
    },
];

/** Giulia, as *A person* shows her to an `admin`. */
export const personDetail: App.Data.PersonDetailData = {
    id: 3,
    first_name: 'Giulia',
    last_name: 'Bianchi',
    initials: 'GB',
    photo: null,
    role: 'child',
    role_label: 'Child',
    birth_date: '2016-05-24',
    age: 10,
    tax_code: 'BNCGLI16E64A944K',
    notes: null,
    account_label: null,
    is_me: false,
    can_edit: true,
    can_delete: true,
};

/** A document of Giulia, as the rows of the lists show it. */
export const healthCard: App.Data.DocumentData = {
    id: 18,
    title: 'Tessera sanitaria',
    category: 'Anagrafica e pratiche',
    person: 'Giulia',
    organization: null,
    date: '2020-10-20',
    expiry_date: '2026-10-20',
    status: 'active',
    mime_type: 'application/pdf',
    file_url: '/files/21',
};

/** Three organizations of the Bianchis, by name. */
export const organizationsList: App.Data.OrganizationData[] = [
    {
        id: 5,
        name: 'BPER Banca',
        types: ['bank'],
        type_labels: ['Bank or lender'],
        logo: null,
        records: ['Conto BPER'],
    },
    {
        id: 1,
        name: 'Edison',
        types: ['utility_provider'],
        type_labels: ['Utility provider'],
        logo: null,
        records: ['Luce - Edison'],
    },
    {
        id: 2,
        name: 'Enel Energia',
        types: ['utility_provider'],
        type_labels: ['Utility provider'],
        logo: null,
        records: ['Gas - Enel'],
    },
];

/** Edison, as *An organization* shows it to an `admin`. */
export const organizationDetail: App.Data.OrganizationDetailData = {
    id: 1,
    name: 'Edison',
    types: ['utility_provider'],
    type_labels: ['Utility provider'],
    logo: null,
    email: null,
    phone: '800 123 456',
    website: 'edison.it',
    notes: 'Codice cliente 40291837',
    can_edit: true,
    can_delete: true,
};

/** The types of organization, as the server gives them. */
export const organizationTypes: App.Data.OptionData[] = [
    { value: 'bank', label: 'Bank or lender', help: null },
    { value: 'utility_provider', label: 'Utility provider', help: null },
    { value: 'shop', label: 'Shop or service', help: null },
];

/** Who a person can be in the family. */
export const personRoles: App.Data.OptionData[] = [
    { value: 'parent', label: 'Parent', help: null },
    { value: 'child', label: 'Child', help: null },
    { value: 'other', label: 'Other', help: null },
];

/** The Panda as a row: inspection in 75 days. */
export const pandaRow: App.Data.CardData = {
    type: 'vehicle',
    id: 1,
    type_label: 'Vehicle',
    label: 'Fiat Panda',
    area: 'vehicles',
    area_label: 'Vehicles',
    subtitle: 'GH 482 TK',
    next_label: 'Inspection',
    next_date: '2027-01-09',
    overdue: false,
    closed: false,
    due_amount: null,
    due_late: false,
};

/** The description of a policy, as the form receives it. */
export const policyFields: App.Data.CardFieldData[] = [
    {
        column: 'title',
        label: 'Title',
        kind: 'text',
        required: true,
        options: [],
        show_when: {},
        label_when: {},
    },
    {
        column: 'type',
        label: 'Type',
        kind: 'dropdown',
        required: true,
        show_when: {},
        label_when: {},
        options: [
            { value: 'car', label: 'Car', help: null },
            { value: 'home', label: 'Home', help: null },
        ],
    },
    {
        column: 'vehicle_id',
        label: 'Vehicle',
        kind: 'card',
        required: false,
        show_when: { type: ['car'] },
        label_when: {},
        options: [{ value: '1', label: 'Fiat Panda', help: null }],
    },
    {
        column: 'home_id',
        label: 'Residence',
        kind: 'card',
        required: false,
        show_when: { type: ['home'] },
        label_when: {},
        options: [{ value: '1', label: 'Casa di via Roma', help: null }],
    },
];

/** The description of a utility, as the form receives it: the supply point code is named by the type. */
export const utilityFields: App.Data.CardFieldData[] = [
    {
        column: 'type',
        label: 'Type',
        kind: 'dropdown',
        required: true,
        show_when: {},
        label_when: {},
        options: [
            { value: 'electricity', label: 'Electricity', help: null },
            { value: 'gas', label: 'Gas', help: null },
        ],
    },
    {
        column: 'supply_point_code',
        label: 'POD code',
        kind: 'text',
        required: false,
        options: [],
        show_when: { type: ['electricity', 'gas'] },
        label_when: { type: { electricity: 'POD code', gas: 'PDR code' } },
    },
];

const edison: App.Data.CardData = {
    ...pandaRow,
    type: 'utility',
    id: 3,
    type_label: 'Utility',
    label: 'Luce - Edison',
    area: 'home',
    area_label: 'Home',
    subtitle: 'Edison',
    next_label: null,
    next_date: null,
};

/** *Casa* of the Bianchis: one overdue card, one closed. */
export const homeArea: App.Data.AreaData = {
    area: 'home',
    label: 'Home',
    count: 2,
    overdue: 1,
    cards: [
        {
            ...pandaRow,
            type: 'home',
            id: 1,
            type_label: 'Residence',
            label: 'Casa di via Roma',
            area: 'home',
            area_label: 'Home',
            subtitle: 'Via Roma 12, Bologna',
            next_label: null,
            next_date: null,
        },
        {
            ...pandaRow,
            type: 'belonging',
            id: 2,
            type_label: 'Belonging',
            label: 'TV Samsung',
            area: 'home',
            area_label: 'Home',
            next_label: 'Warranty end',
            next_date: '2026-10-25',
            overdue: true,
        },
        { ...edison, id: 9, label: 'Luce - Enel', closed: true },
    ],
};

/** The nine areas, *Casa* and *Veicoli* with their cards, the others empty. */
export const allAreas: App.Data.AreaData[] = [
    { ...homeArea, cards: [...homeArea.cards, edison], count: 3 },
    {
        area: 'vehicles',
        label: 'Vehicles',
        count: 1,
        overdue: 0,
        cards: [pandaRow],
    },
    ...(
        [
            'insurance',
            'banks',
            'health',
            'school',
            'work',
            'subscriptions',
            'paperwork',
        ] as const
    ).map((area): App.Data.AreaData => ({
        area,
        label: area,
        count: 0,
        overdue: 0,
        cards: [],
    })),
];

/** The card types, as *New card* lists them. */
export const cardTypes: App.Data.CardTypeData[] = [
    {
        type: 'home',
        label: 'Residence',
        description: 'The home where you live, or a second home',
        area: 'home',
        area_label: 'Home',
    },
    {
        type: 'utility',
        label: 'Utility',
        description: 'Electricity, gas, water, internet, waste tax',
        area: 'home',
        area_label: 'Home',
    },
    {
        type: 'vehicle',
        label: 'Vehicle',
        description: 'Car, motorbike, scooter',
        area: 'vehicles',
        area_label: 'Vehicles',
    },
];

/** The detail of the policy of the Panda. */
export const policyDetail: App.Data.CardDetailData = {
    card: {
        ...pandaRow,
        type: 'insurance_policy',
        id: 1,
        type_label: 'Insurance policy',
        label: 'RC auto Panda - UnipolSai',
        area: 'insurance',
        area_label: 'Insurance',
        next_label: 'Expiry',
        next_date: '2026-11-20',
    },
    heading: 'Car · policyholder Marco Bianchi',
    dates: [{ label: 'Expiry', date: '2026-11-20', expires: true }],
    notices: [],
    values: [{ label: 'Type', text: 'Car', href: null }],
    related: [pandaRow],
    addable: [],
    documents: [healthCard],
    can_edit: true,
    can_delete: true,
    can_delete_documents: true,
    documents_previous: null,
    documents_next: null,
    budget: null,
};

/** The nine areas with their active cards, as the sidebar receives them. */
export const areaCounts: App.Data.AreaCountData[] = allAreas.map(
    ({ area, label, count }): App.Data.AreaCountData => ({
        area,
        label,
        count,
    }),
);

/** A file of *Upload* waiting for its document, as the server sends it. */
export const pendingPayslip: App.Data.PendingFileData = {
    id: 12,
    name: 'Busta paga novembre 2026.pdf',
    mime_type: 'application/pdf',
    size: 120000,
    url: '/files/12',
    title: 'Busta paga novembre 2026',
    preview_url: '/files/12/preview',
    links: [],
};

/** The card of the dance lessons, as `DeadlineData` sends it. */
export const danceEnrollmentRow: App.Data.CardData = {
    type: 'enrollment',
    id: 1,
    type_label: 'Enrollment',
    label: 'Danza Giulia',
    area: 'school',
    area_label: 'School and activities',
    subtitle: '',
    next_label: null,
    next_date: null,
    overdue: false,
    closed: false,
    due_amount: null,
    due_late: false,
};

/** The dance lessons of 05/12/2026: the amount is changed from 45 to 60 euros, with a note. */
export const danceDeadline: App.Data.DeadlineData = {
    kind: 'expense',
    planned_id: 4,
    title: 'Retta danza',
    planned_title: 'Retta danza',
    date: '2026-12-05',
    original_date: '2026-12-05',
    amount: 60,
    base_amount: 45,
    estimated: false,
    notes: 'Comprende la quota del saggio di Natale',
    late: false,
    skipped: false,
    payment: null,
    account_id: 2,
    account: 'Carta Laura',
    card: danceEnrollmentRow,
    income_type: null,
    person: 'Giulia',
};

/** The dance lessons of 05/11/2026, already paid on 20/10. */
export const paidDeadline: App.Data.DeadlineData = {
    ...danceDeadline,
    date: '2026-11-05',
    original_date: '2026-11-05',
    amount: 45,
    base_amount: null,
    notes: null,
    payment: {
        kind: 'expense',
        id: 31,
        date: '2026-10-20',
        amount: 45,
        account: 'Carta Laura',
    },
};

/** The September electricity bill: due on 23/10, late on 26/10. */
export const lateDeadline: App.Data.DeadlineData = {
    kind: 'expense',
    planned_id: 7,
    title: 'Bolletta luce di settembre',
    planned_title: 'Luce',
    date: '2026-10-23',
    original_date: '2026-10-23',
    amount: 71.3,
    base_amount: null,
    estimated: true,
    notes: null,
    late: true,
    skipped: false,
    payment: null,
    account_id: null,
    account: null,
    card: null,
    income_type: null,
    person: null,
};

/** The October salary of Marco, expected on 27/10/2026. */
export const salaryDeadline: App.Data.DeadlineData = {
    kind: 'income',
    planned_id: 2,
    title: 'Stipendio di Marco',
    planned_title: 'Stipendio di Marco',
    date: '2026-10-27',
    original_date: '2026-10-27',
    amount: 1850,
    base_amount: null,
    estimated: false,
    notes: null,
    late: false,
    skipped: false,
    payment: null,
    account_id: 1,
    account: 'Conto BPER',
    card: {
        ...danceEnrollmentRow,
        type: 'employment',
        id: 3,
        type_label: 'Employment',
        label: 'Marco - Studio Rinaldi',
        area: 'work',
        area_label: 'Work',
    },
    income_type: 'salary',
    person: 'Marco',
};

/** The accounts of the family as options of a choice. */
export const accountOptions: App.Data.OptionData[] = [
    { value: '1', label: 'Conto BPER', help: null },
    { value: '2', label: 'Carta Laura', help: null },
];

/** The last six months of the chart, October 2026 in progress. */
export const monthBars: App.Data.MonthBarData[] = [
    { month: '2026-05', spent: 1488.4, planned: 1520, current: false },
    { month: '2026-06', spent: 1602.1, planned: 1540, current: false },
    { month: '2026-07', spent: 1710.3, planned: 1495, current: false },
    { month: '2026-08', spent: 1395.8, planned: 1470, current: false },
    { month: '2026-09', spent: 1655.2, planned: 1610, current: false },
    { month: '2026-10', spent: 1284.2, planned: 1469.3, current: true },
];

/** The dance lessons as *Planned expenses* lists them (45,00 € every month, on the 5th). */
export const danceRow: App.Data.PlannedRowData = {
    kind: 'expense',
    id: 4,
    title: 'Retta danza',
    card: danceEnrollmentRow,
    unit: 'month',
    interval: 1,
    start_date: '2025-09-05',
    end_date: null,
    recurring: true,
    person: 'Giulia',
    next_date: '2026-12-05',
    account: 'Carta Laura',
    amount: 45,
    estimated: false,
    icon: 'graduation-cap',
    color: '#E5A50A',
    ended: false,
};

/** The gas bill: an estimate, for the whole family, with the card of the supply. */
export const gasRow: App.Data.PlannedRowData = {
    ...danceRow,
    id: 9,
    title: 'Gas',
    card: {
        ...danceEnrollmentRow,
        type: 'utility',
        id: 2,
        label: 'Gas - Enel',
        area: 'home',
        area_label: 'Home',
    },
    start_date: '2026-01-30',
    person: 'Marco',
    next_date: '2026-10-30',
    account: 'Conto BPER',
    amount: 55,
    estimated: true,
    icon: 'flame',
    color: '#F76B15',
};

/** Netflix: every month from 04/11/2026, on Laura's card. */
export const netflixRow: App.Data.PlannedRowData = {
    ...danceRow,
    id: 11,
    title: 'Netflix',
    card: null,
    start_date: '2026-11-04',
    person: 'Laura',
    next_date: '2026-11-04',
    amount: 13.99,
    icon: 'clapperboard',
    color: '#E35169',
};

/** The salary of Marco, every month on the 27th. */
export const salaryRow: App.Data.PlannedRowData = {
    ...danceRow,
    kind: 'income',
    id: 2,
    title: 'Stipendio di Marco',
    card: salaryDeadline.card,
    start_date: '2026-01-27',
    person: 'Marco',
    next_date: '2026-10-27',
    account: 'Conto BPER',
    amount: 1850,
    icon: null,
    color: null,
};

/** The exceptions of the dance lessons, the latest first. */
export const danceExceptions: App.Data.ExceptionData[] = [
    {
        id: 3,
        occurrence_date: '2026-12-05',
        type: 'amount_override',
        amount_override: 60,
        date_override: null,
        notes: 'Comprende la quota del saggio di Natale',
        deadline: danceDeadline,
    },
    {
        id: 2,
        occurrence_date: '2026-08-05',
        type: 'skip',
        amount_override: null,
        date_override: null,
        notes: 'Vacanze',
        deadline: {
            ...danceDeadline,
            title: 'Retta di agosto',
            date: '2026-08-05',
            original_date: '2026-08-05',
            amount: 45,
            base_amount: null,
            notes: 'Vacanze',
            skipped: true,
        },
    },
    {
        id: 1,
        occurrence_date: '2026-07-05',
        type: 'date_override',
        amount_override: null,
        date_override: '2026-07-10',
        notes: null,
        deadline: {
            ...danceDeadline,
            date: '2026-07-10',
            original_date: '2026-07-05',
            amount: 45,
            base_amount: null,
            notes: null,
            late: true,
        },
    },
];

/** A payment of the dance lessons. */
export const danceMovement: App.Data.MovementData = {
    kind: 'expense',
    id: 31,
    title: 'Retta di novembre',
    date: '2026-10-20',
    amount: 45,
    fee_amount: null,
    account_id: 2,
    account: 'Carta Laura',
    category_id: 8,
    category: 'Sport',
    icon: 'dumbbell',
    color: '#E5A50A',
    organization_id: 5,
    organization: 'Scuola Passo a Due',
    person_id: 4,
    card: null,
    planned_id: 4,
    occurrence_date: '2026-11-05',
    type: null,
    employment_id: null,
    documents: [],
    can_edit: true,
    can_delete: true,
};

/** The page of the dance lessons: the year, the next four due dates, the exceptions, the data and the payments. */
export const danceDetail: App.Data.PlannedDetailData = {
    row: danceRow,
    year_total: 465,
    skipped_months: [7, 8],
    next: [
        paidDeadline,
        danceDeadline,
        {
            ...danceDeadline,
            date: '2027-01-05',
            original_date: '2027-01-05',
            amount: 45,
            base_amount: null,
            notes: null,
        },
        {
            ...danceDeadline,
            date: '2027-02-05',
            original_date: '2027-02-05',
            amount: 45,
            base_amount: null,
            notes: null,
        },
    ],
    exceptions: danceExceptions,
    values: [
        { label: 'Category', text: 'Scuola e attività › Sport', href: null },
        {
            label: 'Organization',
            text: 'Scuola Passo a Due',
            href: '/family/organizations/5',
        },
        { label: 'Account', text: 'Carta Laura', href: null },
        { label: 'Holder', text: 'Giulia', href: '/family/people/4' },
        {
            label: 'Refers to',
            text: 'Danza Giulia',
            href: '/cards/enrollment/1',
        },
    ],
    payments: [{ year: 2026, total: 135, count: 3, payments: [danceMovement] }],
    can_edit: true,
    can_pay: true,
};

/** The choices of the form of a planned expense. */
export const plannedExpenseOptions = {
    accounts: accountOptions,
    categories: [
        { value: '1', label: 'Casa e affitto', help: null },
        { value: '2', label: 'Svago', help: null },
        { value: '3', label: 'Bollette e utenze', help: null },
    ],
    organizations: [{ value: '5', label: 'Netflix', help: null }],
    people: [
        { value: '3', label: 'Laura Bianchi', help: null },
        { value: '4', label: 'Giulia Bianchi', help: null },
    ],
    cards: [
        {
            value: 'subscription:7',
            label: 'Subscription · Netflix',
            help: null,
        },
        { value: 'utility:3', label: 'Utility · Luce - Edison', help: null },
    ],
    category_card_types: { '1': 'home', '3': 'utility' },
    card_type_labels: {
        home: 'Home',
        subscription: 'Subscription',
        utility: 'Utility',
    },
    employments: [],
    income_types: [],
};

/** The starting values of the form, as the server sends them for a new planned expense. */
export const newPlannedValues = {
    title: '',
    amount: null,
    is_estimated: false,
    notes: null,
    start_date: null,
    end_date: null,
    is_recurring: true,
    interval: 1,
    interval_unit: 'month',
    organization_id: null,
    financial_account_id: null,
    person_id: null,
    category_id: null,
    card: null,
};

/** The choices of the form of a movement of the Bianchi. */
export const movementOptions: MovementFormOptions = {
    accounts: accountOptions,
    categories: [
        { value: '8', label: 'Svago › Sport', help: null },
        { value: '9', label: 'Salute', help: null },
        { value: '10', label: 'Auto', help: null },
        { value: '11', label: 'Bollette e utenze', help: null },
    ],
    organizations: [
        { value: '5', label: 'Scuola Passo a Due', help: null },
        { value: '6', label: 'Studio Ferri', help: null },
    ],
    people: [
        { value: '3', label: 'Laura Bianchi', help: null },
        { value: '4', label: 'Giulia Bianchi', help: null },
    ],
    planned: [
        {
            kind: 'expense',
            value: '4',
            label: 'Retta danza',
            category_id: '8',
            card: 'enrollment:3',
            organization_id: '5',
            financial_account_id: '2',
            person_id: '4',
            employment_id: null,
            type: null,
        },
        {
            kind: 'expense',
            value: '7',
            label: 'Bollo Panda',
            category_id: '10',
            card: 'vehicle:1',
            organization_id: null,
            financial_account_id: '1',
            person_id: null,
            employment_id: null,
            type: null,
        },
        {
            kind: 'income',
            value: '2',
            label: 'Stipendio di Laura',
            category_id: null,
            card: null,
            organization_id: '6',
            financial_account_id: '1',
            person_id: '3',
            employment_id: '1',
            type: 'salary',
        },
    ],
    cards: [
        {
            value: 'enrollment:3',
            label: 'Enrollment · Danza Giulia',
            help: null,
        },
        {
            value: 'health_event:2',
            label: 'Health event · Visite di Giulia',
            help: null,
        },
        { value: 'utility:3', label: 'Utility · Luce - Edison', help: null },
        { value: 'vehicle:1', label: 'Vehicle · Panda', help: null },
    ],
    category_card_types: {
        '8': 'enrollment',
        '9': 'health_event',
        '10': 'vehicle',
        '11': 'utility',
    },
    card_type_labels: {
        enrollment: 'Enrollment',
        health_event: 'Health event',
        utility: 'Utility',
        vehicle: 'Vehicle',
    },
    employments: [
        { value: '1', label: 'Laura - Comune di Bologna', help: null },
        { value: '2', label: 'Marco - Studio Rinaldi', help: null },
    ],
    income_types: [
        { value: 'salary', label: 'Stipendio', help: null },
        { value: 'other', label: 'Altro', help: null },
    ],
};

/** October 2026 as *Movements* sums it up. */
export const octoberSummary: App.Data.MonthSummaryData = {
    month: '2026-10',
    incomes: 1720,
    salaries_in: 1,
    salaries_expected: 2,
    expenses: 1284.2,
    payments: 15,
    balance: 435.8,
};

/** The salary of Laura received on 23/10/2026. */
export const salaryMovement: App.Data.MovementData = {
    ...danceMovement,
    kind: 'income',
    id: 12,
    title: 'Stipendio di ottobre',
    date: '2026-10-23',
    amount: 1720,
    account_id: 1,
    account: 'Conto BPER',
    category_id: null,
    category: null,
    icon: null,
    color: null,
    organization_id: 6,
    organization: 'Comune di Bologna',
    person_id: 3,
    planned_id: 2,
    occurrence_date: '2026-10-23',
    type: 'salary',
    employment_id: 1,
};

/** October 2026 as *Planned and spent* sends it on 26/10/2026 (the Bianchi). */
export const octoberComparison: App.Data.ComparisonData = {
    month: '2026-10',
    planned: 1469.3,
    spent: 1284.2,
    incomes: 1720,
    incomes_planned: 3570,
    days_left: 5,
    categories: [
        {
            name: 'Casa e affitto',
            icon: 'house',
            color: '#2563eb',
            spent: 612,
            planned: 612,
            estimated: false,
            late_title: null,
        },
        {
            name: 'Spesa',
            icon: 'shopping-basket',
            color: '#16a34a',
            spent: 150.1,
            planned: 320,
            estimated: true,
            late_title: null,
        },
        {
            name: 'Bollette e utenze',
            icon: 'zap',
            color: '#e35169',
            spent: 29.9,
            planned: 223.3,
            estimated: false,
            late_title: 'Luce',
        },
        {
            name: 'Salute',
            icon: 'heart-pulse',
            color: '#d9467a',
            spent: 108.4,
            planned: 0,
            estimated: false,
            late_title: null,
        },
    ],
    months: monthBars,
    unpaid: [
        lateDeadline,
        {
            ...lateDeadline,
            planned_id: 8,
            title: 'Bolletta gas',
            planned_title: 'Gas',
            date: '2026-10-30',
            original_date: '2026-10-30',
            amount: 84.2,
            late: false,
        },
    ],
};

/** The electricity of the Bianchis, as a row. */
export const luceRow: App.Data.CardData = edison;

/** The planned expense of the electricity: 72 € a month, estimated, on the 23rd. */
export const lucePlanned: App.Data.PlannedRowData = {
    ...gasRow,
    id: 7,
    title: 'Luce',
    card: edison,
    start_date: '2026-01-23',
    next_date: '2026-11-06',
    amount: 72,
    icon: 'zap',
};

/** The October bill, moved to 06/11 with its amount. */
export const luceOctober: App.Data.DeadlineData = {
    ...lateDeadline,
    title: 'Bolletta luce di ottobre',
    date: '2026-11-06',
    original_date: '2026-11-23',
    amount: 62.4,
    base_amount: 72,
    late: false,
    skipped: false,
    account_id: 1,
    account: 'Conto BPER',
    card: edison,
};

/**
 * A bill of the electricity paid from the Conto BPER.
 *
 * @param id - the id of the expense
 * @param title - its title
 * @param date - the day it was paid
 * @param amount - the amount paid
 * @returns the movement, as `MovementData` sends it
 */
export function luceBill(
    id: number,
    title: string,
    date: string,
    amount: number,
): App.Data.MovementData {
    return {
        ...danceMovement,
        id,
        title,
        date,
        amount,
        account_id: 1,
        account: 'Conto BPER',
        category: 'Luce',
        icon: 'zap',
        color: '#F76B15',
        planned_id: 7,
        occurrence_date: date,
    };
}

/** The payments of the electricity by year: three bills of 2026 and one of 2025. */
export const lucePayments: App.Data.PaymentYearData[] = [
    {
        year: 2026,
        total: 642.1,
        count: 3,
        payments: [
            luceBill(41, 'Bolletta luce di agosto', '2026-09-23', 68.9),
            luceBill(40, 'Bolletta luce di luglio', '2026-08-22', 74.2),
            luceBill(39, 'Bolletta luce di giugno', '2026-07-23', 61.5),
        ],
    },
    {
        year: 2025,
        total: 82.3,
        count: 1,
        payments: [
            luceBill(30, 'Bolletta luce di novembre', '2025-12-23', 82.3),
        ],
    },
];

/** What the page of the electricity shows of its payments on 26/10/2026. */
export const luceBudget: App.Data.CardBudgetData = {
    kind: 'expense',
    next: null,
    next_label: '',
    installment_number: null,
    installments_count: null,
    installments_paid: null,
    paid_amount: null,
    left_amount: null,
    numbers: [
        {
            label: 'Planned',
            amount: 72,
            count: null,
            hint: 'per month, estimated',
            hint_amount: null,
        },
        {
            label: 'Paid in 2026',
            amount: 642.1,
            count: null,
            hint: '9 bills',
            hint_amount: null,
        },
    ],
    due_label: 'To pay',
    due: [{ ...lateDeadline, card: edison }, luceOctober],
    planned_label: 'Expected expenses',
    planned: [lucePlanned],
    can_add_planned: true,
    payments_label: 'Bills paid',
    payments_more: 'See all the bills, year by year',
    payments: lucePayments,
    pay: [{ ...lateDeadline, card: edison }],
    can_pay: true,
};

/** The policy of the Panda with its renewal, its premium and the premium paid in 2025. */
export const policyBudget: App.Data.CardBudgetData = {
    ...luceBudget,
    next: {
        ...danceDeadline,
        planned_id: 12,
        title: 'Assicurazione auto',
        planned_title: 'Assicurazione auto',
        date: '2026-11-21',
        original_date: '2026-11-21',
        amount: 418,
        base_amount: null,
        notes: null,
        account: 'Conto BPER',
        card: policyDetail.card,
    },
    next_label: 'Renewal',
    numbers: [
        {
            label: 'Premium',
            amount: 418,
            count: null,
            hint: 'every year',
            hint_amount: null,
        },
    ],
    due_label: null,
    due: [],
    planned: [
        {
            ...lucePlanned,
            id: 12,
            title: 'Assicurazione auto',
            unit: 'year',
            start_date: '2025-11-21',
            estimated: false,
        },
    ],
    payments_label: 'Premiums paid',
    payments_more: 'See all the premiums, year by year',
    payments: [
        {
            year: 2025,
            total: 418,
            count: 1,
            payments: [luceBill(20, 'Assicurazione auto', '2025-11-21', 418)],
        },
    ],
};

/** The description of the fields of a loan that its totals follow, as the form receives it. */
export const loanFields: App.Data.CardFieldData[] = [
    ['name', 'Name', 'text', true],
    ['total_amount', 'Amount financed', 'currency', false],
    ['installments_count', 'Number of instalments', 'number', false],
    ['installment_amount', 'Instalment', 'currency', false],
    [
        'final_installment_amount',
        'Amount of the last instalment',
        'currency',
        false,
    ],
    ['start_date', 'Start', 'date', false],
]
    .map(([column, label, kind, required]): App.Data.CardFieldData => ({
        column: String(column),
        label: String(label),
        kind: String(kind),
        required: required === true,
        options: [],
        show_when: {},
        label_when: {},
    }))
    .concat([
        {
            column: 'status',
            label: 'Status',
            kind: 'choice',
            required: true,
            options: [
                { value: 'active', label: 'Active', help: null },
                { value: 'paid_off', label: 'Paid off', help: null },
            ],
            show_when: {},
            label_when: {},
        },
    ]);
