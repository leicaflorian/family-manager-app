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
