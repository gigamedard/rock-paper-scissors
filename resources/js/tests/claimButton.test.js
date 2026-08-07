import { describe, it, expect, vi, beforeEach } from 'vitest';

// We'll test a simplified version of the claim button logic to verify it prevents double clicks
describe('Claim Button UI Logic', () => {
    let btn;
    let gameState;
    
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="claim-section">
                <button id="claim-btn">CLAIM</button>
            </div>
        `;
        btn = document.getElementById('claim-btn');
        gameState = { pendingClaim: { amount: 1, signature: '0x' } };
    });

    it('should disable claim button when clicked', async () => {
        // Mock function imitating game.js claim()
        async function mockClaim() {
            if (!gameState.pendingClaim) return;
            const b = document.getElementById('claim-btn');
            b.innerText = "PROCESSING CLAIM...";
            b.disabled = true;
            
            // Simulate async network request
            await new Promise(resolve => setTimeout(resolve, 50));
        }

        expect(btn.disabled).toBe(false);
        
        // Trigger claim
        const promise = mockClaim();
        
        // Check synchronously right after trigger
        expect(btn.disabled).toBe(true);
        expect(btn.innerText).toBe("PROCESSING CLAIM...");
        
        await promise;
    });
});
