<?php

declare(strict_types=1);

/*
 * The labels of the enums of fixed choices (English), read by
 * App\Enums\Concerns\HasLabel: the enum name in snake case, then the value.
 */
return [
    'belonging_status' => [
        'in_use' => 'In use',
        'disposed' => 'Disposed of',
    ],
    'document_status' => [
        'active' => 'Active',
        'archived' => 'Archived',
        'expired' => 'Expired',
    ],
    'employment_status' => [
        'active' => 'Active',
        'ended' => 'Ended',
    ],
    'enrollment_status' => [
        'active' => 'Active',
        'ended' => 'Ended',
    ],
    'enrollment_type' => [
        'school' => 'School',
        'sport' => 'Sport',
        'course' => 'Course',
        'other' => 'Other',
    ],
    'exception_type' => [
        'skip' => 'Skipped',
        'amount_override' => 'Different amount',
        'date_override' => 'Different date',
    ],
    'filing_status' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
    'filing_type' => [
        'tax_return' => 'Tax return (730)',
        'isee' => 'ISEE',
        'benefit' => 'Allowances and bonuses',
        'parental_leave' => 'Maternity and leave',
        'municipal' => 'Municipal paperwork',
        'other' => 'Other',
    ],
    'financial_account_status' => [
        'active' => 'Active',
        'closed' => 'Closed',
    ],
    'financial_account_type' => [
        'checking_account' => 'Current account',
        'card' => 'Card',
        'deposit' => 'Deposit',
        'online_wallet' => 'Online account',
        'other' => 'Other',
    ],
    'health_event_type' => [
        'visit' => 'Visit',
        'exam' => 'Test',
        'vaccine' => 'Vaccine',
        'prescription' => 'Prescription',
        'optical' => 'Eyecare',
    ],
    'home_status' => [
        'active' => 'Active',
        'archived' => 'Archived',
    ],
    'income_type' => [
        'salary' => 'Salary',
        'benefit' => 'INPS and bonuses',
        'refund' => 'Refund',
        'other' => 'Other',
    ],
    'insurance_policy_status' => [
        'active' => 'Active',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
    ],
    'insurance_policy_type' => [
        'car' => 'Car',
        'life' => 'Life',
        'home' => 'Home',
        'health' => 'Health',
        'accident' => 'Accident',
        'fund' => 'Health fund',
        'other' => 'Other',
    ],
    'interval_unit' => [
        'week' => 'Week',
        'month' => 'Month',
        'year' => 'Year',
    ],
    'loan_status' => [
        'active' => 'Active',
        'paid_off' => 'Paid off',
    ],
    'maintenance_type' => [
        'service' => 'Service',
        'inspection' => 'MOT inspection',
        'repair' => 'Repair',
        'boiler_service' => 'Boiler service',
        'other' => 'Other',
    ],
    'organization_type' => [
        'bank' => 'Bank or lender',
        'insurance' => 'Insurance',
        'utility_provider' => 'Utility provider',
        'employer' => 'Employer',
        'public_office' => 'Public office',
        'school' => 'School or association',
        'healthcare' => 'Healthcare',
        'shop' => 'Shop or service',
        'other' => 'Other',
    ],
    'person_role' => [
        'parent' => 'Parent',
        'child' => 'Child',
        'other' => 'Other',
    ],
    'reminder_status' => [
        'open' => 'Open',
        'done' => 'Done',
    ],
    'role' => [
        'admin' => 'Everything, like you',
        'member' => 'Sees everything and uploads documents',
    ],
    'role_description' => [
        'admin' => 'Settings, invitations and the subscription too',
        'member' => 'Pays, records and uploads, but does not change the settings',
    ],
    'role_name' => [
        'admin' => 'Administrator',
        'member' => 'Family member',
    ],
    'subscription_status' => [
        'active' => 'Active',
        'cancelled' => 'Cancelled',
    ],
    'subscription_type' => [
        'streaming' => 'Streaming and entertainment',
        'software' => 'Software and licences',
        'cloud' => 'Cloud and storage',
        'web' => 'Domains, hosting and email',
        'other' => 'Other',
    ],
    'utility_status' => [
        'active' => 'Active',
        'terminated' => 'Terminated',
    ],
    'utility_type' => [
        'electricity' => 'Electricity',
        'gas' => 'Gas',
        'water' => 'Water',
        'internet' => 'Internet',
        'phone' => 'Phone',
        'waste_tax' => 'Waste tax (TARI)',
        'tv_license' => 'TV licence',
    ],
    'vehicle_status' => [
        'active' => 'Active',
        'sold' => 'Sold',
    ],
];
