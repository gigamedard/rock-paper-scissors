(function () {
    console.log("Initializing Mock API...");

    const originalFetch = window.fetch;
    const MOCK_DELAY = 500; // Simulate network latency

    // Mock Data Store
    const mockStore = {
        user: {
            id: 1,
            address: null, // Will be set on login
            balance: "1000",
            referral_code: "MOCK123",
            referrals_count: 5,
            rewards: "50",
            is_influencer: false
        },
        token: "mock-jwt-token",
        stats: {
            total_volume: "1000000",
            floor_price: "0.05",
            active_listings: 150
        }
    };

    // Helper to create a response
    const mockResponse = (data, status = 200) => {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve(new Response(JSON.stringify(data), {
                    status: status,
                    headers: { 'Content-Type': 'application/json' }
                }));
            }, MOCK_DELAY);
        });
    };

    window.fetch = async (url, options = {}) => {
        const urlString = url.toString();
        console.log(`[MockAPI] Fetching: ${urlString}`, options);

        // --- Wallet & Auth ---
        if (urlString.includes('/wallet/generate-message')) {
            return mockResponse({ message: "Sign this message to login: " + Date.now() });
        }

        if (urlString.includes('/wallet/verify-signature')) {
            // Extract address if possible (or just mock it)
            // In a real app we'd verify the signature. Here we just say YES.
            const body = JSON.parse(options.body || '{}');
            mockStore.user.address = body.address || "0xMockAddress...";

            return mockResponse({
                success: true,
                token: mockStore.token,
                user: mockStore.user
            });
        }

        if (urlString.includes('/logout')) {
            return mockResponse({ success: true });
        }

        // --- User Data ---
        if (urlString.endsWith('/user') || urlString.includes('/user?')) {
            return mockResponse(mockStore.user);
        }

        if (urlString.includes('/user/pre-moves')) {
            return mockResponse([]); // No pre-moves
        }

        if (urlString.includes('/user/set-referral')) {
            return mockResponse({ success: true, message: "Referral set!" });
        }

        // --- Referral ---
        if (urlString.includes('/referral/validate')) {
            return mockResponse({ valid: true });
        }

        if (urlString.includes('/referral/leaderboard')) {
            return mockResponse([
                { address: "0x123...abc", count: 150, rank: 1 },
                { address: "0x456...def", count: 120, rank: 2 },
                { address: "0x789...ghi", count: 90, rank: 3 },
                { address: "0xabc...jkl", count: 50, rank: 4 },
                { address: "0xdef...mno", count: 10, rank: 5 }
            ]);
        }

        // --- Influencer ---
        if (urlString.includes('/influencer/dashboard')) {
            return mockResponse({
                is_influencer: true,
                stats: {
                    clicks: 1200,
                    conversions: 50,
                    earnings: "500"
                },
                pool: {
                    name: "Alpha Pool",
                    reward: "1000"
                }
            });
        }

        if (urlString.includes('/influencer/application-status')) {
            return mockResponse({ status: 'approved' });
        }

        // --- Marketplace ---
        if (urlString.includes('/marketplace/stats')) {
            return mockResponse(mockStore.stats);
        }

        if (urlString.includes('/marketplace/trades')) {
            return mockResponse([
                { id: 1, price: "0.05", item: "Rock #1", seller: "0x1..." },
                { id: 2, price: "0.06", item: "Paper #5", seller: "0x2..." },
                { id: 3, price: "0.04", item: "Scissors #9", seller: "0x3..." }
            ]);
        }

        // --- Admin ---
        if (urlString.includes('/admin/applications')) {
            return mockResponse([]);
        }

        // Fallback to original fetch for other things (like images, though they should be local)
        // If it's a relative path to a file, let it pass.
        if (!urlString.startsWith('http') && !urlString.startsWith('/')) {
            return originalFetch(url, options);
        }

        // If it's an absolute URL to an external service (like Pinata or Fonts), let it pass
        if (urlString.startsWith('http')) {
            return originalFetch(url, options);
        }

        console.warn(`[MockAPI] Unhandled URL: ${urlString}`);
        return mockResponse({ error: "Mock not implemented for this endpoint" }, 404);
    };

})();
