<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cards</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Card Management</h1>

        <!-- Create Card Form -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-indigo-600">Create New Card</h2>
            <form id="create-card-form" class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" id="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Effect Type</label>
                    <select id="effect_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                        <option value="cooldown_reduction">Cooldown Reduction (0.5 = 50%)</option>
                        <option value="ceiling_increase">Ceiling Increase (Multiplier)</option>
                        <option value="base_bet_modifier">Base Bet Modifier (+ amount)</option>
                        <option value="ki_regeneration">KI Regeneration</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Effect Value</label>
                    <input type="number" step="0.01" id="effect_value" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Price (SNT)</label>
                    <input type="number" step="0.01" id="price" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Duration Type</label>
                    <select id="duration_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                        <option value="sessions">Sessions (count)</option>
                        <option value="time">Time (hours)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Duration Value</label>
                    <input type="number" id="duration_value" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" required>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border"></textarea>
                </div>
                <div class="col-span-2 flex justify-end">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition">Create Card</button>
                </div>
            </form>
        </div>

        <!-- Cards List -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Existing Cards</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effect</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price (SNT)</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 bg-gray-50"></th>
                        </tr>
                    </thead>
                    <tbody id="cards-tbody" class="bg-white divide-y divide-gray-200">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api/admin/cards';
        const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token'); // Make sure the user has an auth token

        async function fetchCards() {
            try {
                const res = await fetch(API_BASE, {
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
                });
                if (!res.ok) {
                    if (res.status === 401 || res.status === 403) {
                        renderError('You must be logged in as an Administrator to view and manage cards.');
                    } else {
                        renderError('Error loading cards: ' + res.statusText);
                    }
                    return;
                }
                const cards = await res.json();
                if (Array.isArray(cards)) {
                    renderCards(cards);
                } else {
                    renderError('Invalid cards data received.');
                }
            } catch (e) {
                console.error('Error fetching cards', e);
                renderError('Connection error occurred while fetching cards.');
            }
        }

        function renderError(message) {
            const tbody = document.getElementById('cards-tbody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-sm font-medium text-red-500 bg-red-50">
                        ⚠️ ${message}
                    </td>
                </tr>
            `;
        }

        function renderCards(cards) {
            const tbody = document.getElementById('cards-tbody');
            tbody.innerHTML = '';
            if (cards.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                            No cards available.
                        </td>
                    </tr>
                `;
                return;
            }
            cards.forEach(card => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${card.name}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${card.effect_type} (${card.effect_value})</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-green-600">${card.price} SNT</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${card.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            ${card.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button onclick="deleteCard(${card.id})" class="text-red-600 hover:text-red-900">Delete</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        document.getElementById('create-card-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = {
                name: document.getElementById('name').value,
                description: document.getElementById('description').value,
                effect_type: document.getElementById('effect_type').value,
                effect_value: document.getElementById('effect_value').value,
                price: document.getElementById('price').value,
                duration_type: document.getElementById('duration_type').value,
                duration_value: document.getElementById('duration_value').value,
                is_active: 1
            };

            try {
                await fetch(API_BASE, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token
                    },
                    body: JSON.stringify(data)
                });
                fetchCards();
            } catch (e) { alert('Failed to create card'); }
        });

        window.deleteCard = async function(id) {
            if(!confirm('Are you sure?')) return;
            try {
                await fetch(API_BASE + '/' + id, {
                    method: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
                });
                fetchCards();
            } catch (e) { alert('Failed to delete card'); }
        }

        fetchCards();
    </script>
</body>
</html>
