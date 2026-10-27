<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The categories a new family starts with, for documents and expenses.
 *
 * Each tree is `main category name => [subcategory names]`, in display order.
 * The names are English translation keys: a new family gets them in the
 * language of whoever registers it, and can change them from the settings.
 */
final class DefaultCategories
{
    /**
     * The document categories, as in the vault note *Categorie*.
     *
     * @return array<string, list<string>>
     */
    public static function documents(): array
    {
        return [
            'Personal records and paperwork' => [],
            'Home and utilities' => ['Electricity', 'Gas', 'Water', 'Internet', 'Phone', 'Waste tax (TARI)', 'TV licence'],
            'Vehicles' => [],
            'Purchases, warranties and subscriptions' => ['Furniture', 'Electronics and appliances', 'Warranties and receipts', 'Subscriptions'],
            'Insurance and funds' => [],
            'Banks and loans' => [],
            'Taxes and social security' => ['Tax return (730)', 'ISEE', 'Allowances and bonuses', 'Maternity and leave'],
            'Work' => ['Payslips', 'Annual income statement (CU)', 'VAT number'],
            'Health' => ['Visits and tests', 'Prescriptions', 'Vaccinations', 'Eyecare', 'Medical invoices'],
            'School and activities' => [],
        ];
    }

    /**
     * The icon (a Lucide name) and colour (an area) of the main document
     * categories, as in the board *Categorie dei documenti*. Subcategories take
     * the colour of their main category.
     *
     * @return array<string, array{icon: string, color: string}>
     */
    public static function documentLooks(): array
    {
        return [
            'Personal records and paperwork' => ['icon' => 'clipboard-list', 'color' => 'paperwork'],
            'Home and utilities' => ['icon' => 'house', 'color' => 'home'],
            'Vehicles' => ['icon' => 'car', 'color' => 'vehicles'],
            'Purchases, warranties and subscriptions' => ['icon' => 'shopping-bag', 'color' => 'subscriptions'],
            'Insurance and funds' => ['icon' => 'shield', 'color' => 'insurance'],
            'Banks and loans' => ['icon' => 'landmark', 'color' => 'banks'],
            'Taxes and social security' => ['icon' => 'receipt', 'color' => 'general'],
            'Work' => ['icon' => 'briefcase', 'color' => 'work'],
            'Health' => ['icon' => 'heart-pulse', 'color' => 'health'],
            'School and activities' => ['icon' => 'graduation-cap', 'color' => 'school'],
        ];
    }

    /**
     * The expense categories, those of Family Manager on Directus.
     *
     * @return array<string, list<string>>
     */
    public static function expenses(): array
    {
        return [
            'School and nursery' => [],
            'Home and rent' => [],
            'Donations' => [],
            'Bills and utilities' => ['Electricity', 'Gas', 'Water'],
            'Leisure' => [],
            'Work' => [],
            'Car' => [],
            'Savings' => [],
            'Policies and insurance' => [],
            'Health' => [],
            'Groceries' => [],
            'Loans and financing' => [],
        ];
    }
}
