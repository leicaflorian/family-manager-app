import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { bankGroups } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import BankGroupProposal from './BankGroupProposal.vue';

describe('BankGroupProposal', () => {
    it('says the proposal, or that no similar expense was found', () => {
        const view = renderPage(BankGroupProposal, { group: bankGroups[0] });
        expect(
            screen.getByText(
                'Proposal: create the expense «Ricarica Satispay» · Varie · Satispay',
            ),
        ).toBeInTheDocument();
        view.unmount();

        renderPage(BankGroupProposal, { group: bankGroups[3] });
        expect(
            screen.getByText(
                'No similar expense found. When you create the filter, the AI suggests title and category.',
            ),
        ).toBeInTheDocument();
    });
});
