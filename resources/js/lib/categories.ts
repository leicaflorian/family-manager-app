/**
 * The document categories as the menus of the forms list them.
 */

/** An option of a menu: the value that is sent and the text that is read. */
export type Option = { value: string; label: string };

/**
 * Every category of the tree, in its order, each with its path: a subcategory
 * reads «Work › Payslips».
 *
 * @param tree - the main categories, each with its subcategories
 * @param path - the names of the categories above, for the recursion
 * @returns the options, the id as value
 */
export function categoryOptions(
    tree: App.Data.CategoryData[],
    path: string[] = [],
): Option[] {
    return tree.flatMap((category) => {
        const names = [...path, category.name];

        return [
            { value: String(category.id), label: names.join(' › ') },
            ...categoryOptions(category.children, names),
        ];
    });
}
