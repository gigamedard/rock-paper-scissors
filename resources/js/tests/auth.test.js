import { describe, it, expect, vi } from 'vitest';
import { parseRpcError } from '../core/auth.js';

// Mock window.t (translation function)
window.t = (key) => key;

describe('parseRpcError', () => {
    it('should format insufficient funds error', () => {
        const error = new Error("insufficient funds for gas * price + value");
        expect(parseRpcError(error)).toBe('errors.insufficient_funds');
    });

    it('should format user rejected transaction error', () => {
        const error = new Error("MetaMask Tx Signature: User rejected transaction");
        expect(parseRpcError(error)).toBe('errors.user_rejected');
    });

    it('should extract reason from execution reverted error', () => {
        const error = new Error('execution reverted: "Claim payment failed" (action="estimateGas", data="0x...", reason="Claim payment failed")');
        expect(parseRpcError(error)).toBe('Action refusée par le contrat : Claim payment failed');
    });

    it('should return default message for unknown revert without reason', () => {
        const error = new Error('execution reverted without a reason');
        expect(parseRpcError(error)).toBe('Transaction rejetée par le Smart Contract (conditions non remplies).');
    });
});
