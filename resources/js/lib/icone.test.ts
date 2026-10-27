import { File, Folder, House } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import { iconaCategoria } from './icone';

describe('iconaCategoria', () => {
    it("dà l'icona di Lucide dal nome salvato nella categoria", () => {
        expect(iconaCategoria('house')).toBe(House);
    });

    it('senza nome, o con un nome che non conosce, dà la cartella', () => {
        expect(iconaCategoria(null)).toBe(Folder);
        expect(iconaCategoria('non-esiste')).toBe(Folder);
        expect(iconaCategoria('file')).not.toBe(File);
    });
});
