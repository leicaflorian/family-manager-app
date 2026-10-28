/**
 * Fake data for the tests of the pages, the data more than one file needs.
 */

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
    },
    {
        column: 'type',
        label: 'Type',
        kind: 'dropdown',
        required: true,
        show_when: {},
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
        options: [{ value: '1', label: 'Fiat Panda', help: null }],
    },
    {
        column: 'home_id',
        label: 'Residence',
        kind: 'card',
        required: false,
        show_when: { type: ['home'] },
        options: [{ value: '1', label: 'Casa di via Roma', help: null }],
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
};

/** The nine areas with their active cards, as the sidebar receives them. */
export const areaCounts: App.Data.AreaCountData[] = allAreas.map(
    ({ area, label, count }): App.Data.AreaCountData => ({
        area,
        label,
        count,
    }),
);
