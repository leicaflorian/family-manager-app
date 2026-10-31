/**
 * The cases of `FmCategoryTree` for the component catalogue: the tree of the
 * document categories with their counts, one that can be tapped to edit and
 * an empty one.
 */
import { defineExamples } from '@/catalog/examples';
import FmCategoryTree from './FmCategoryTree.vue';

/** The categories of the Bianchis' documents, with the number of documents of each. */
const categories: App.Data.CategoryData[] = [
    {
        id: 1,
        name: 'Casa e utenze',
        parent_id: null,
        icon: 'home',
        color: 'home',
        documents_count: 5,
        children: [
            {
                id: 2,
                name: 'Luce',
                parent_id: 1,
                icon: null,
                color: 'home',
                documents_count: 3,
                children: [],
            },
            {
                id: 5,
                name: 'Altro',
                parent_id: 1,
                icon: null,
                color: 'home',
                documents_count: 2,
                children: [],
            },
        ],
    },
    {
        id: 3,
        name: 'Lavoro',
        parent_id: null,
        icon: 'briefcase',
        color: 'work',
        documents_count: 0,
        children: [],
    },
];

export default defineExamples(FmCategoryTree, [
    { name: 'Tree', props: { categories } },
    { name: 'Selectable', props: { categories, selectable: true } },
    { name: 'Empty', props: { categories: [] } },
]);
