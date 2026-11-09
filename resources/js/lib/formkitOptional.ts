import type { FormKitExtendableSchemaRoot, FormKitNode } from '@formkit/core';
import { trans } from 'laravel-vue-i18n';
import { cn } from '@/lib/utils';

/**
 * A FormKit plugin: a field with the prop `optional` writes «(optional)» after
 * its label, in the soft body text, as the board *Nuova-spesa* draws *Ente* and
 * *Scheda collegata*. The label stays the label of the field, so screen readers
 * say both.
 *
 * @param node - a field that FormKit is creating
 */
export function markOptional(node: FormKitNode): void {
    node.addProps(['optional', 'optionalText', 'optionalClass']);

    const definition = node.props.definition;
    const schema = definition?.schema;

    // A box (checkbox, radio) uses the label section for the text of each
    // option: replacing it would leave the options without their names
    if (
        definition === undefined ||
        typeof schema !== 'function' ||
        definition.family === 'box'
    ) {
        return;
    }

    node.props.optionalText = trans('(optional)');
    node.props.optionalClass = cn('text-body-sm text-ink-soft');

    // The label is added to the sections the page gives (the Vue prop
    // sectionsSchema), as `keepDropdownList` does with the list.
    const withMark: FormKitExtendableSchemaRoot = (sections) =>
        schema({
            ...sections,
            label: {
                children: [
                    '$label',
                    // The space outside the mark: inside, the name of the
                    // field read by screen readers lost it
                    {
                        $el: null,
                        if: '$optional === true || $optional === ""',
                        children: ' ',
                    },
                    {
                        $el: 'span',
                        // Written bare in a template the prop arrives empty
                        if: '$optional === true || $optional === ""',
                        attrs: { class: '$optionalClass' },
                        children: '$optionalText',
                    },
                ],
            },
        });

    node.props.definition = {
        ...definition,
        schema: withMark,
        // The schema is kept by this key: the fields with the mark need their own
        schemaMemoKey:
            definition.schemaMemoKey === undefined
                ? undefined
                : `${definition.schemaMemoKey}_optional`,
    };
}
