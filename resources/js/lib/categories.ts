/**
 * The document categories as the menus of the forms list them.
 */

/** An option of a menu: the value that is sent and the text that is read. */
export type Option = { value: string; label: string };

/**
 * A category in a menu. The label is its path («Work › Payslips»): the closed
 * field shows it and the search reads it. The open list shows only the name,
 * indented by the depth.
 */
export type CategoryOption = Option & {
    /** The name of the category alone. */
    name: string;
    /** 0 for a main category, 1 for its subcategories, and so on. */
    depth: number;
};

/**
 * Every category of the tree, in its order, each with its name, its depth and
 * its path.
 *
 * @param tree - the main categories, each with its subcategories
 * @param path - the names of the categories above, for the recursion
 * @returns the options, the id as value
 */
export function categoryOptions(
    tree: App.Data.CategoryData[],
    path: string[] = [],
): CategoryOption[] {
    return tree.flatMap((category) => {
        const names = [...path, category.name];

        return [
            {
                value: String(category.id),
                label: names.join(' › '),
                name: category.name,
                depth: path.length,
            },
            ...categoryOptions(category.children, names),
        ];
    });
}
