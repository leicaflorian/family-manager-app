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
    },
    links: [
        {
            type: 'employment',
            id: 3,
            type_label: 'Employments',
            label: 'Laura - Comune di Bologna',
        },
    ],
    can_edit: true,
    can_delete: true,
};

/** The choices of the filters of *Documents*. */
export const documentFilterOptions = {
    categories: [
        {
            id: 3,
            name: 'Lavoro',
            parent_id: null,
            icon: 'briefcase',
            color: 'work',
            documents_count: 0,
            children: [],
        },
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
        records: ['Conto BPER'],
    },
    {
        id: 1,
        name: 'Edison',
        types: ['utility_provider'],
        type_labels: ['Utility provider'],
        records: ['Luce - Edison'],
    },
    {
        id: 2,
        name: 'Enel Energia',
        types: ['utility_provider'],
        type_labels: ['Utility provider'],
        records: ['Gas - Enel'],
    },
];

/** Edison, as *An organization* shows it to an `admin`. */
export const organizationDetail: App.Data.OrganizationDetailData = {
    id: 1,
    name: 'Edison',
    types: ['utility_provider'],
    type_labels: ['Utility provider'],
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
