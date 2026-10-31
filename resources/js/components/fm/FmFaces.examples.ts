/**
 * The cases of `FmFaces` for the component catalogue: the three Bianchis, two
 * people, and a small row.
 */
import { defineExamples } from '@/catalog/examples';
import { peopleList } from '@/test/fixtures';
import FmFaces from './FmFaces.vue';

export default defineExamples(FmFaces, [
    { name: 'The family', props: { people: peopleList } },
    { name: 'Two people', props: { people: peopleList.slice(0, 2) } },
    { name: 'Small', props: { people: peopleList, size: 32 } },
]);
