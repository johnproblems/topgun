@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center mb-4">
                <svg class="w-8 h-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <h1 class="text-2xl font-bold text-red-800">License Required</h1>
            </div>
            
            <div class="text-red-700 mb-6">
                <p class="mb-4">{{ session('error', 'A valid license is required to access this feature.') }}</p>
                
                @if(session('required_features'))
                    <div class="mb-4">
                        <p class="font-semibold mb-2">Required Features:</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach(session('required_features') as $feature)
                                <li>{{ ucwords(str_replace('_', ' ', $feature)) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                <a href="{{ route('license.purchase') }}" 
                   class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                    Purchase License
                </a>
                
                <a href="{{ route('dashboard') }}" 
                   class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors ml-4">
                    Return to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
@endsection