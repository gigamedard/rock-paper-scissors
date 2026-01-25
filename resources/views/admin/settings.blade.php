<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Admin Settings</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">Game Configuration</h1>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.settings.update') }}" method="POST">
            @csrf
            
            @if($settings->count() > 0)
                @foreach($settings as $group => $groupSettings)
                    <div class="mb-8 border-b pb-4">
                        <h2 class="text-xl font-semibold mb-4 capitalize text-indigo-600">{{ $group }} Settings</h2>
                        
                        <div class="grid grid-cols-1 gap-6">
                            @foreach($groupSettings as $setting)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ $setting->key }} 
                                        @if($setting->description)
                                            <span class="text-xs text-gray-500">- {{ $setting->description }}</span>
                                        @endif
                                    </label>
                                    
                                    @if($setting->type === 'boolean')
                                        <select name="{{ $setting->key }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200">
                                            <option value="1" {{ $setting->value ? 'selected' : '' }}>True</option>
                                            <option value="0" {{ !$setting->value ? 'selected' : '' }}>False</option>
                                        </select>
                                    @else
                                        <input type="text" name="{{ $setting->key }}" value="{{ $setting->value }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 p-2 border">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition">
                        Save Changes
                    </button>
                </div>
            @else
                <div class="text-center text-gray-500 py-10">
                    No settings found. Please run seeder or add settings manually.
                </div>
            @endif
        </form>
    </div>
</body>
</html>
